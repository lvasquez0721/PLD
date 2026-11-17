#!/usr/bin/env python3
"""
Genera datos de prueba para disparar todos los patrones de alertamiento y
notificación automáticos del PLD.

Uso:
    pip install requests pymysql
    python3 scripts/generar_datos_prueba_pld.py --usuario <usuario> --password <password>

Si el certificado del servidor es autofirmado (por ejemplo UAT), agrega --insecure.

Opciones útiles:
    --dry-run     Valida .env, conexión MySQL, catálogos y configuración de correo.
                  No crea datos, no autentica y no envía correos.
    --limpiar     Al terminar, elimina los datos generados por esta ejecución.
    --yes         No pide confirmación (los correos se enviarán con la config actual).
    --insecure    Desactiva la validación TLS (útil para entornos locales con certificado propio).

El script usa:
  - API HTTP para alta de clientes, emisión de operaciones y registro de pagos.
  - MySQL directo para preparar casos deterministas (lista negra, PPE, perfil
    transaccional) y para verificar el resultado.
"""

from __future__ import annotations

import argparse
import getpass
import json
import os
import random
import re
import string
import sys
from datetime import date, timedelta
from pathlib import Path
from typing import Any

try:
    import requests
except ImportError:
    print("Falta la dependencia 'requests'. Instala con: pip install requests pymysql", file=sys.stderr)
    raise SystemExit(2)

try:
    import pymysql
    from pymysql.cursors import DictCursor
except ImportError:
    print("Falta la dependencia 'pymysql'. Instala con: pip install requests pymysql", file=sys.stderr)
    raise SystemExit(2)


ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = ROOT / ".env"


class SeedError(RuntimeError):
    pass


def load_env(path: Path) -> dict[str, str]:
    data: dict[str, str] = {}
    if not path.exists():
        return data

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
            value = value[1:-1]
        data[key.strip()] = value

    return data


ENV = load_env(ENV_PATH)


def str2bool(value: str | bool) -> bool:
    if isinstance(value, bool):
        return value
    return str(value).strip().lower() in ("1", "true", "si", "sí", "yes", "y", "on")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Genera clientes, operaciones, pagos, alertas y correos de prueba del PLD.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )

    parser.add_argument("--base-url", default=os.getenv("PLD_BASE_URL", ENV.get("APP_URL")), help="URL raíz de la aplicación.")
    parser.add_argument("--usuario", default=os.getenv("PLD_API_USER"), help="Usuario para POST /api/login.")
    parser.add_argument("--password", default=os.getenv("PLD_API_PASSWORD"), help="Contraseña para POST /api/login.")
    parser.add_argument("--timeout", type=int, default=120, help="Timeout HTTP en segundos.")
    parser.add_argument("--insecure", action="store_true", help="No validar certificados TLS.")
    parser.add_argument("--dry-run", action="store_true", help="Solo valida configuración y conexión; no escribe datos.")
    parser.add_argument("--limpiar", action="store_true", help="Elimina al final los datos generados por esta ejecución.")
    parser.add_argument("--yes", action="store_true", help="No pedir confirmación antes de enviar correos reales.")
    parser.add_argument("--prefijo", default=None, help="Prefijo para RFC, nombres y pólizas. Se genera uno único si se omite.")

    parser.add_argument("--db-host", default=os.getenv("PLD_DB_HOST", ENV.get("DB_HOST")))
    parser.add_argument("--db-port", type=int, default=int(os.getenv("PLD_DB_PORT", ENV.get("DB_PORT", "3306"))))
    parser.add_argument("--db-name", default=os.getenv("PLD_DB_NAME", ENV.get("DB_DATABASE")))
    parser.add_argument("--db-user", default=os.getenv("PLD_DB_USER", ENV.get("DB_USERNAME")))
    parser.add_argument("--db-password", default=os.getenv("PLD_DB_PASSWORD", ENV.get("DB_PASSWORD")))

    return parser


class PLDSeeder:
    def __init__(self, args: argparse.Namespace):
        self.args = args
        self.base_url = (args.base_url or "").rstrip("/")
        if not self.base_url:
            raise SeedError("No se encontró APP_URL ni se recibió --base-url.")

        if self.base_url.endswith("/api"):
            self.api_root = self.base_url
        else:
            self.api_root = self.base_url + "/api"

        self.session = requests.Session()
        self.session.verify = not args.insecure
        self.session.headers.update({"Accept": "application/json"})

        self.run_token = "".join(random.choices(string.ascii_uppercase + string.digits, k=6))
        self.prefix = (args.prefijo or f"PY{date.today():%y%m%d}{self.run_token}").upper()
        self.prefix = re.sub(r"[^A-Z0-9]", "", self.prefix)[:10] or "PYPLD"

        self.db = self._connect_db()
        self.cur = self.db.cursor()

        self.created_clients: set[int] = set()
        self.created_operations: set[int] = set()
        self.created_list_records: set[int] = set()
        self.operation_info: dict[int, dict[str, Any]] = {}
        self.results: list[dict[str, Any]] = []

        self.initial_max_log_id = 0
        self.initial_max_alert_id = 0

        self.low_state: dict[str, Any] = {}
        self.high_state: dict[str, Any] = {}
        self.low_occupation: dict[str, Any] = {}
        self.high_occupation: dict[str, Any] = {}
        self.param_relevante = 7500.0
        self.param_minimo_alerta = 7500.0
        self.official: dict[str, Any] = {}

    # ------------------------------------------------------------------ DB

    def _connect_db(self):
        missing = [
            name
            for name, value in {
                "DB_HOST": self.args.db_host,
                "DB_DATABASE": self.args.db_name,
                "DB_USERNAME": self.args.db_user,
            }.items()
            if not value
        ]
        if missing:
            raise SeedError(f"Faltan datos de conexión MySQL: {', '.join(missing)}")

        return pymysql.connect(
            host=self.args.db_host,
            port=self.args.db_port,
            user=self.args.db_user,
            password=self.args.db_password or "",
            database=self.args.db_name,
            charset="utf8mb4",
            cursorclass=DictCursor,
            autocommit=True,
        )

    def q1(self, sql: str, params: tuple | list = ()) -> dict[str, Any] | None:
        self.cur.execute(sql, params)
        return self.cur.fetchone()

    def qall(self, sql: str, params: tuple | list = ()) -> list[dict[str, Any]]:
        self.cur.execute(sql, params)
        return list(self.cur.fetchall())

    def execute(self, sql: str, params: tuple | list = ()) -> int:
        self.cur.execute(sql, params)
        return int(self.cur.rowcount)

    # -------------------------------------------------------------- helpers

    def api_url(self, path: str) -> str:
        return f"{self.api_root}/{path.lstrip('/')}"

    def make_rfc(self, tag: str) -> str:
        raw = re.sub(r"[^A-Z0-9]", "", f"{self.prefix}{tag}{self.run_token}".upper())
        return raw[:18]

    def make_name(self, tag: str) -> str:
        return f"PRUEBA PLD {tag} {self.prefix} {self.run_token}"[:255]

    def make_folio(self, tag: str, endoso: str | None = None) -> str:
        folio = f"{self.prefix}-{tag}-{self.run_token}"[:40]
        if endoso:
            folio = f"{folio}-{endoso}"[:40]
        return folio

    def add_result(self, case: str, check: str, ok: bool, detail: str = "") -> None:
        self.results.append({"caso": case, "verificacion": check, "ok": bool(ok), "detalle": detail})
        mark = "OK" if ok else "ERROR"
        print(f"  [{mark}] {check}: {detail}" if detail else f"  [{mark}] {check}")

    # ------------------------------------------------------------ catálogos

    def load_catalogs(self) -> None:
        self.low_state = self.q1(
            "SELECT IDEstado, Estado, IndicePaz FROM catEstados "
            "WHERE IDEstado > 0 AND CAST(IndicePaz AS DECIMAL(10,2)) < 3 "
            "ORDER BY IDEstado LIMIT 1"
        ) or {}
        self.high_state = self.q1(
            "SELECT IDEstado, Estado, IndicePaz FROM catEstados "
            "WHERE IDEstado > 0 AND CAST(IndicePaz AS DECIMAL(10,2)) >= 3 "
            "ORDER BY CAST(IndicePaz AS DECIMAL(10,2)) DESC, IDEstado LIMIT 1"
        ) or {}

        self.low_occupation = self.q1(
            "SELECT IDOcupacionGiro, CVE_GIRO, OcupacionGiro, NivelRiesgo "
            "FROM catOcupacionesGiros "
            "WHERE NivelRiesgo <> 3 AND CVE_GIRO REGEXP '^[1-9][0-9]{5,6}$' "
            "ORDER BY IDOcupacionGiro LIMIT 1"
        ) or {}
        self.high_occupation = self.q1(
            "SELECT IDOcupacionGiro, CVE_GIRO, OcupacionGiro, NivelRiesgo "
            "FROM catOcupacionesGiros "
            "WHERE NivelRiesgo = 3 AND CVE_GIRO REGEXP '^[1-9][0-9]{5,6}$' "
            "ORDER BY IDOcupacionGiro LIMIT 1"
        ) or {}

        param1 = self.q1("SELECT Valor FROM catParametriaPLD WHERE IDParametro = 1 LIMIT 1")
        param14 = self.q1("SELECT Valor FROM catParametriaPLD WHERE IDParametro = 14 LIMIT 1")
        if param1 and param1.get("Valor") is not None:
            self.param_relevante = float(param1["Valor"])
        if param14 and param14.get("Valor") is not None:
            self.param_minimo_alerta = float(param14["Valor"])

        missing = []
        if not self.low_state:
            missing.append("catEstados con IndicePaz < 3")
        if not self.high_state:
            missing.append("catEstados con IndicePaz >= 3")
        if not self.low_occupation:
            missing.append("catOcupacionesGiros de bajo riesgo")
        if not self.high_occupation:
            missing.append("catOcupacionesGiros con NivelRiesgo = 3")
        if missing:
            raise SeedError("No se encontraron catálogos requeridos: " + ", ".join(missing))

    def check_official_config(self) -> None:
        self.official = self.q1(
            "SELECT IDCorreo, Correo, Nombre FROM catCorreoNotificaciones "
            "WHERE Archivo = 'OficialCumplimiento' AND Activo = 1 "
            "ORDER BY IDCorreo LIMIT 1"
        ) or {}
        if not self.official:
            raise SeedError(
                "No existe configuración activa de OficialCumplimiento. "
                "Configúrala antes de ejecutar el script (Archivo = OficialCumplimiento, Activo = 1)."
            )

    def check_api_reachable(self) -> None:
        try:
            response = self.session.get(self.api_url("login"), timeout=min(self.args.timeout, 20))
            print(f"  API: {self.api_root} respondió HTTP {response.status_code}")
        except requests.RequestException as exc:
            raise SeedError(f"No se pudo acceder a {self.api_root}: {exc}") from exc

    # --------------------------------------------------------------- HTTP

    def request(self, method: str, path: str, payload: dict | None = None) -> dict[str, Any]:
        url = self.api_url(path)
        try:
            response = self.session.request(method, url, json=payload, timeout=self.args.timeout)
        except requests.RequestException as exc:
            raise SeedError(f"{method} {url} falló: {exc}") from exc

        try:
            data = response.json()
        except ValueError as exc:
            raise SeedError(f"{method} {url} devolvió {response.status_code} sin JSON: {response.text[:500]}") from exc

        if response.status_code >= 400:
            raise SeedError(f"{method} {url} devolvió HTTP {response.status_code}: {json.dumps(data, ensure_ascii=False)[:1200]}")

        return data

    def login(self) -> None:
        usuario = self.args.usuario
        password = self.args.password

        if not usuario:
            raise SeedError("Falta --usuario o la variable PLD_API_USER.")
        if not password:
            password = getpass.getpass(f"Contraseña para {usuario}: ")

        data = self.request("POST", "login", {"usuario": usuario, "password": password})
        token = data.get("token")
        if not data.get("success") or not token:
            raise SeedError(f"Login fallido: {json.dumps(data, ensure_ascii=False)[:800]}")

        self.session.headers.update({"Authorization": f"Bearer {token}"})
        print(f"  Autenticado como {usuario}")

    # --------------------------------------------------------- API helpers

    def create_client_api(
        self,
        tag: str,
        occupation: dict[str, Any],
        state: dict[str, Any],
        rfc: str | None = None,
    ) -> tuple[int, dict[str, Any]]:
        client_rfc = rfc or self.make_rfc(tag)
        payload = {
            "RFC": client_rfc,
            "nombre": self.make_name(tag),
            "apellidoPaterno": "PRUEBA",
            "apellidoMaterno": "PLD",
            "IDTipoPersona": 1,
            "IDNacionalidad": "MX",
            "IDOcupacionGiro": int(occupation["CVE_GIRO"]),
            "domicilios": [
                {
                    "calle": "CALLE PRUEBA PLD",
                    "noExterior": "123",
                    "noInterior": None,
                    "colonia": "COLONIA PRUEBA",
                    "CP": "01000",
                    "IDEstado": str(state["IDEstado"]),
                    "municipio": "MUNICIPIO PRUEBA",
                    "localidad": "LOCALIDAD PRUEBA",
                    "telefono": "5555555555",
                }
            ],
        }

        data = self.request("POST", "clientes/guardarCliente", payload)
        if int(data.get("codigoError", 1)) != 0:
            raise SeedError(f"Alta de cliente {tag} falló: {json.dumps(data, ensure_ascii=False)[:1200]}")

        client_id = int(data["IDCliente"])
        self.created_clients.add(client_id)
        print(
            f"  Cliente API {tag}: ID {client_id} | RFC {client_rfc} | "
            f"ocupación {occupation['CVE_GIRO']} | estado {state['IDEstado']} ({state['Estado']})"
        )
        return client_id, data

    def create_client_db(
        self,
        tag: str,
        occupation: dict[str, Any],
        state: dict[str, Any],
        es_ppe: bool = False,
    ) -> int:
        client_rfc = self.make_rfc(tag)
        name = self.make_name(tag)

        self.cur.execute(
            """
            INSERT INTO tbClientes
                (ckAgente, RFC, Nombre, ApellidoPaterno, ApellidoMaterno, IDTipoPersona,
                 IDOcupacionGiro, CoincideEnListasNegras, EsPPEActivo, EsAltoRiesgo,
                 IDNacionalidad, Activo, TipoPersona, created_at, updated_at)
            VALUES
                (0, %s, %s, 'PRUEBA', 'PLD', 1, %s, 0, %s, 0, 'MX', 1, 'Cliente', NOW(), NOW())
            """,
            (client_rfc, name, int(occupation["CVE_GIRO"]), 1 if es_ppe else 0),
        )
        client_id = int(self.cur.lastrowid)

        self.cur.execute(
            """
            INSERT INTO tbClientesDomicilio
                (IDCliente, Calle, NoExterior, Colonia, CP, IDEstado, Municipio,
                 Localidad, Telefono, created_at, updated_at)
            VALUES
                (%s, 'CALLE PRUEBA PLD', '123', 'COLONIA PRUEBA', '01000', %s,
                 'MUNICIPIO PRUEBA', 'LOCALIDAD PRUEBA', '5555555555', NOW(), NOW())
            """,
            (client_id, str(state["IDEstado"])),
        )

        self.created_clients.add(client_id)
        print(
            f"  Cliente BD {tag}: ID {client_id} | RFC {client_rfc} | "
            f"ocupación {occupation['CVE_GIRO']} | estado {state['IDEstado']} ({state['Estado']}) | PPE {int(es_ppe)}"
        )
        return client_id

    def insert_list_record(self, rfc: str, nombre: str) -> int:
        self.cur.execute(
            """
            INSERT INTO tbListasNegraCNSF
                (Nombre, RFC, UsuarioAlta, TimeStampAlta, created_at, updated_at)
            VALUES
                (%s, %s, 'SCRIPT PYTHON', NOW(), NOW(), NOW())
            """,
            (nombre, rfc),
        )
        record_id = int(self.cur.lastrowid)
        self.created_list_records.add(record_id)
        return record_id

    def insert_profile_alto_riesgo(self, client_id: int) -> None:
        self.cur.execute(
            """
            INSERT INTO tbPerfilTransaccional
                (IDCliente, Perfil, `FechaEjecucción`, IDTipoEjecuccion, created_at, updated_at)
            VALUES
                (%s, '2.000000', CURDATE(), 1, NOW(), NOW())
            """,
            (client_id,),
        )

    def create_operation(
        self,
        client_id: int,
        tag: str,
        prima: float,
        gastos: float = 0.0,
        folio: str | None = None,
        folio_endoso: str | None = None,
        fecha_emision: date | None = None,
        inicio_vigencia: date | None = None,
        fin_vigencia: date | None = None,
        es_cancelacion: bool = False,
    ) -> tuple[int, dict[str, Any]]:
        inicio = inicio_vigencia or date.today()
        fin = fin_vigencia or (inicio + timedelta(days=365))
        emision = fecha_emision or date.today()
        folio_poliza = folio or self.make_folio(tag)

        payload = {
            "IDCliente": client_id,
            "FolioPoliza": folio_poliza,
            "FolioEndoso": folio_endoso,
            "FechaEmision": emision.isoformat(),
            "PrimaTotal": round(float(prima), 2),
            "IDMoneda": "USD",
            "FechaInicioVigencia": inicio.isoformat(),
            "FechaFinVigencia": fin.isoformat(),
            "GastosEmision": round(float(gastos), 2),
            "EsEndosoCancelacion": bool(es_cancelacion),
            "PagaTercero": False,
        }

        data = self.request("POST", "insertar/operacion", payload)
        if int(data.get("codigoError", 1)) != 0:
            raise SeedError(
                f"Emisión de operación {tag} falló: {json.dumps(data, ensure_ascii=False)[:1200]}"
            )

        operation_id = int(data["IDOperacion"])
        self.created_operations.add(operation_id)
        self.operation_info[operation_id] = {
            "folio": folio_poliza,
            "folio_endoso": folio_endoso,
            "client_id": client_id,
        }
        print(
            f"  Operación {tag}: ID {operation_id} | cliente {client_id} | póliza {folio_poliza} | "
            f"prima {prima:.2f} USD | gastos {gastos:.2f}"
        )
        return operation_id, data

    def pay_operation(
        self,
        operation_id: int,
        client_id: int,
        amounts: list[float],
        forma_pago_id: int,
    ) -> dict[str, Any]:
        info = self.operation_info.get(operation_id, {})
        total = round(sum(float(a) for a in amounts), 2)

        detalles = []
        for amount in amounts:
            detalles.append(
                {
                    "IDMoneda": "USD",
                    "TipoCambio": 1,
                    "IDCliente": client_id,
                    "PagaTercero": False,
                    "IDOperacion": operation_id,
                    "folioPoliza": info.get("folio"),
                    "folioEndoso": info.get("folio_endoso"),
                    "detalleMontoPagado": round(float(amount), 2),
                }
            )

        payload = {
            "montoPagado": total,
            "IDFormaPago": str(forma_pago_id),
            "FechaPago": date.today().isoformat(),
            "detallePagos": detalles,
        }

        data = self.request("POST", "insertar/operacion-pago", payload)
        if int(data.get("codigoError", 1)) != 0:
            raise SeedError(
                f"Pago de operación {operation_id} falló: {json.dumps(data, ensure_ascii=False)[:1200]}"
            )

        print(
            f"  Pagos operación {operation_id}: {amounts} USD | forma {forma_pago_id} | "
            f"alertas reportadas por API: {data.get('alertasGeneradas', '—')}"
        )
        return data

    # ---------------------------------------------------------- verificación

    def find_alert(
        self,
        patron: str,
        client_id: int | None = None,
        operation_id: int | None = None,
    ) -> dict[str, Any] | None:
        clauses = ["IDRegistroAlerta > %s", "Patron = %s"]
        params: list[Any] = [self.initial_max_alert_id, patron]

        if client_id is not None:
            clauses.append("IDCliente = %s")
            params.append(client_id)
        if operation_id is not None:
            clauses.append("IDOperacion = %s")
            params.append(operation_id)

        return self.q1(
            "SELECT IDRegistroAlerta, Patron, IDCliente, IDOperacion, Estatus "
            "FROM tbAlertas WHERE " + " AND ".join(clauses) +
            " ORDER BY IDRegistroAlerta DESC LIMIT 1",
            params,
        )

    def find_log(
        self,
        tipo: str,
        client_id: int | None = None,
        operation_id: int | None = None,
        patron: str | None = None,
    ) -> dict[str, Any] | None:
        clauses = ["id > %s", "Tipo = %s"]
        params: list[Any] = [self.initial_max_log_id, tipo]

        if client_id is not None:
            clauses.append("IDCliente = %s")
            params.append(client_id)
        if operation_id is not None:
            clauses.append("IDOperacion = %s")
            params.append(operation_id)
        if patron:
            clauses.append("Patrones LIKE %s")
            params.append(f"%{patron}%")

        return self.q1(
            "SELECT id, Tipo, Estado, IDCliente, IDOperacion, Patrones, Asunto "
            "FROM logCorreos WHERE " + " AND ".join(clauses) +
            " ORDER BY id DESC LIMIT 1",
            params,
        )

    def no_alerts(self, client_id: int | None = None, operation_id: int | None = None) -> bool:
        clauses = ["IDRegistroAlerta > %s"]
        params: list[Any] = [self.initial_max_alert_id]
        if client_id is not None:
            clauses.append("IDCliente = %s")
            params.append(client_id)
        if operation_id is not None:
            clauses.append("IDOperacion = %s")
            params.append(operation_id)
        row = self.q1("SELECT COUNT(*) AS total FROM tbAlertas WHERE " + " AND ".join(clauses), params)
        return int(row["total"] if row else 0) == 0

    def check_alert(
        self,
        case: str,
        patron: str,
        client_id: int | None = None,
        operation_id: int | None = None,
    ) -> dict[str, Any] | None:
        row = self.find_alert(patron, client_id, operation_id)
        if row:
            detail = f"ID {row['IDRegistroAlerta']} | estatus {row['Estatus']}"
            self.add_result(case, f"alerta '{patron}'", True, detail)
        else:
            self.add_result(case, f"alerta '{patron}'", False, "No encontrada")
        return row

    def check_no_alert(self, case: str, operation_id: int, patron: str) -> None:
        row = self.find_alert(patron, operation_id=operation_id)
        self.add_result(
            case,
            f"sin alerta '{patron}'",
            row is None,
            "No se generó" if row is None else f"Se generó inesperadamente ID {row['IDRegistroAlerta']}",
        )

    def check_no_alerts(
        self,
        case: str,
        client_id: int | None = None,
        operation_id: int | None = None,
    ) -> None:
        ok = self.no_alerts(client_id=client_id, operation_id=operation_id)
        self.add_result(
            case,
            "sin alertamientos",
            ok,
            "No se generaron alertas" if ok else "Se encontraron alertas inesperadas",
        )

    def check_log(
        self,
        case: str,
        tipo: str,
        client_id: int | None = None,
        operation_id: int | None = None,
        patron: str | None = None,
    ) -> dict[str, Any] | None:
        row = self.find_log(tipo, client_id, operation_id, patron)
        etiqueta = f"correo {tipo}" + (f" patrón '{patron}'" if patron else "")
        if row:
            estado = str(row.get("Estado") or "")
            detail = f"ID {row['id']} | estado {estado or '—'}"
            self.add_result(case, etiqueta, estado == "Enviado", detail)
        else:
            self.add_result(case, etiqueta, False, "No encontrado en logCorreos")
        return row

    def check_client_flag(self, case: str, client_id: int, field: str, expected: int) -> None:
        allowed = {"EsAltoRiesgo", "CoincideEnListasNegras", "EsPPEActivo", "Activo"}
        if field not in allowed:
            raise ValueError(f"Campo no permitido: {field}")

        row = self.q1(f"SELECT {field} FROM tbClientes WHERE IDCliente = %s LIMIT 1", (client_id,))
        value = int(row[field]) if row and row.get(field) is not None else None
        self.add_result(
            case,
            f"cliente {field} = {expected}",
            value == expected,
            f"valor actual: {value}",
        )

    # ------------------------------------------------------------ escenarios

    def scenario_actividad_vulnerable(self) -> None:
        case = "Actividad vulnerable"
        client_id, _ = self.create_client_api("ACT", self.high_occupation, self.low_state)
        self.check_alert(case, "Actividad vulnerable", client_id=client_id)
        self.check_log(case, "OficialCumplimiento", client_id=client_id, patron="Actividad vulnerable")
        self.check_client_flag(case, client_id, "EsAltoRiesgo", 1)

    def scenario_zona_geografica(self) -> None:
        case = "Zona geografica (sin alerta)"
        client_id, _ = self.create_client_api("ZON", self.low_occupation, self.high_state)
        self.check_no_alerts(case, client_id=client_id)
        self.check_log(case, "OficialCumplimiento", client_id=client_id)
        self.check_client_flag(case, client_id, "EsAltoRiesgo", 1)

    def scenario_personas_bloqueadas(self) -> None:
        case = "Personas Bloqueadas"
        rfc = self.make_rfc("LST")
        nombre = self.make_name("LISTA NEGRA")
        record_id = self.insert_list_record(rfc, nombre)
        print(f"  Lista CNSF: ID {record_id} | RFC {rfc}")

        client_id, _ = self.create_client_api("LST", self.low_occupation, self.low_state, rfc=rfc)
        self.check_alert(case, "Personas Bloqueadas", client_id=client_id)
        self.check_log(case, "OficialCumplimiento", client_id=client_id, patron="Personas Bloqueadas")
        self.check_client_flag(case, client_id, "CoincideEnListasNegras", 1)

    def scenario_ppe_operacion(self) -> None:
        case = "PPE"
        client_id = self.create_client_db("PPE", self.low_occupation, self.low_state, es_ppe=True)
        operation_id, _ = self.create_operation(client_id, "PPE", prima=100.0)
        self.check_alert(case, "PPE", operation_id=operation_id)
        self.check_log(case, "AlertaConsolidada", patron="PPE")

    def scenario_perfil_transaccional(self) -> None:
        case = "Perfil transaccional >= 2 (sin alerta)"
        client_id = self.create_client_db("PERF", self.low_occupation, self.low_state)
        self.insert_profile_alto_riesgo(client_id)
        operation_id, _ = self.create_operation(client_id, "PERF", prima=100.0)
        self.check_no_alerts(case, operation_id=operation_id)
        self.check_log(case, "RiesgoAlto", operation_id=operation_id)

    def scenario_ubicacion_prima(self) -> None:
        case = "Ubicación de riesgo + prima >= parámetro 14 (sin alerta)"
        client_id = self.create_client_db("UBI", self.low_occupation, self.high_state)
        prima = max(self.param_minimo_alerta + 500.0, self.param_relevante + 500.0)
        operation_id, _ = self.create_operation(client_id, "UBI", prima=prima)
        self.check_no_alerts(case, operation_id=operation_id)
        self.check_log(case, "RiesgoAlto", operation_id=operation_id)

    def scenario_fraccionado(self) -> None:
        case = "Fraccionado"
        client_id = self.create_client_db("FRAC", self.low_occupation, self.low_state)
        operation_id, _ = self.create_operation(client_id, "FRAC", prima=1000.0, gastos=0.0)
        self.pay_operation(operation_id, client_id, [400.0, 600.0], forma_pago_id=3)
        self.check_alert(case, "Fraccionado", operation_id=operation_id)
        self.check_log(case, "AlertaConsolidada", patron="Fraccionado")

    def scenario_acumulado_efectivo(self) -> None:
        case = "Acumulado Efectivo"
        client_id = self.create_client_db("ACUM", self.low_occupation, self.low_state)
        operation_id, _ = self.create_operation(client_id, "ACUM", prima=1000.0, gastos=100.0)
        self.pay_operation(operation_id, client_id, [100.0, 900.0], forma_pago_id=1)
        self.check_alert(case, "Acumulado Efectivo", operation_id=operation_id)
        self.check_no_alert(case, operation_id, "Fraccionado")
        self.check_log(case, "AlertaConsolidada", patron="Acumulado Efectivo")

    def scenario_monto(self) -> None:
        case = "Monto"
        client_id = self.create_client_db("MON", self.low_occupation, self.low_state)
        prima = max(self.param_relevante + 500.0, 8000.0)
        operation_id, _ = self.create_operation(client_id, "MON", prima=prima, gastos=0.0)
        self.pay_operation(operation_id, client_id, [prima], forma_pago_id=1)
        self.check_alert(case, "Monto", operation_id=operation_id)
        self.check_log(case, "AlertaConsolidada", patron="Monto")

    def scenario_monto_usual(self) -> None:
        case = "Monto Inusual"
        client_id = self.create_client_db("INU", self.low_occupation, self.low_state)

        for index, prima in enumerate((100.0, 200.0, 300.0), start=1):
            operation_id, _ = self.create_operation(client_id, f"INU-H{index}", prima=prima, gastos=0.0)
            self.pay_operation(operation_id, client_id, [prima], forma_pago_id=1)

        prima_final = 5000.0
        operation_id, _ = self.create_operation(client_id, "INU-FINAL", prima=prima_final, gastos=0.0)
        self.pay_operation(operation_id, client_id, [prima_final], forma_pago_id=1)

        self.check_alert(case, "Monto Inusual", operation_id=operation_id)
        self.check_log(case, "AlertaConsolidada", patron="Monto Inusual")

    def scenario_cancelacion(self) -> None:
        case = "Cancelacion"
        client_id = self.create_client_db("CANC", self.low_occupation, self.low_state)
        folio = self.make_folio("CANC")
        inicio = date.today()
        fin = inicio + timedelta(days=100)

        base_operation, _ = self.create_operation(
            client_id,
            "CANC-BASE",
            prima=1000.0,
            gastos=0.0,
            folio=folio,
            fecha_emision=inicio,
            inicio_vigencia=inicio,
            fin_vigencia=fin,
            es_cancelacion=False,
        )

        cancel_operation, _ = self.create_operation(
            client_id,
            "CANC-ENDOSO",
            prima=-1000.0,
            gastos=0.0,
            folio=folio,
            folio_endoso=self.make_folio("CANC", "1"),
            fecha_emision=inicio + timedelta(days=10),
            inicio_vigencia=inicio,
            fin_vigencia=fin,
            es_cancelacion=True,
        )

        print(f"  Póliza cancelación: base {base_operation} | endoso {cancel_operation} | folio {folio}")
        self.check_alert(case, "Cancelacion", operation_id=cancel_operation)
        self.check_log(case, "AlertaConsolidada", patron="Cancelacion")

    # --------------------------------------------------------------- flujo

    def print_dry_run(self) -> None:
        print("\n=== VALIDACIÓN DRY-RUN ===")
        print(f"  Base URL:              {self.base_url}")
        print(f"  MySQL:                 {self.args.db_host}:{self.args.db_port}/{self.args.db_name}")
        print(f"  Oficial de cumplimiento: {self.official.get('Correo')} ({self.official.get('Nombre') or 'sin nombre'})")
        print(f"  Estado bajo riesgo:    {self.low_state.get('IDEstado')} - {self.low_state.get('Estado')} (IndicePaz {self.low_state.get('IndicePaz')})")
        print(f"  Estado alto riesgo:    {self.high_state.get('IDEstado')} - {self.high_state.get('Estado')} (IndicePaz {self.high_state.get('IndicePaz')})")
        print(f"  Ocupación bajo riesgo: {self.low_occupation.get('CVE_GIRO')} - {self.low_occupation.get('OcupacionGiro')} (NivelRiesgo {self.low_occupation.get('NivelRiesgo')})")
        print(f"  Ocupación alto riesgo: {self.high_occupation.get('CVE_GIRO')} - {self.high_occupation.get('OcupacionGiro')} (NivelRiesgo {self.high_occupation.get('NivelRiesgo')})")
        print(f"  Parámetro 1:           {self.param_relevante:.2f} USD")
        print(f"  Parámetro 14:          {self.param_minimo_alerta:.2f} USD")
        print(f"  Prefijo de ejecución:  {self.prefix}-{self.run_token}")
        print("\n  No se escribieron datos, no se autenticó y no se enviaron correos.")

    def confirm(self) -> None:
        if self.args.yes:
            return

        print("\nADVERTENCIA: se enviarán correos reales con la configuración SMTP actual.")
        print(f"Destinatario configurado: {self.official.get('Correo')}")
        answer = input("¿Continuar? [s/N]: ").strip().lower()
        if answer not in ("s", "si", "sí", "y", "yes"):
            raise SeedError("Ejecución cancelada por el usuario.")

    def cleanup(self) -> None:
        print("\n=== LIMPIEZA DE DATOS GENERADOS ===")

        client_ids = sorted(self.created_clients)
        operation_ids = sorted(self.created_operations)
        list_ids = sorted(self.created_list_records)

        def ph(items: list[int]) -> str:
            return ",".join(["%s"] * len(items))

        alert_ids: list[int] = []
        if client_ids or operation_ids:
            clauses = []
            params: list[Any] = []
            if client_ids:
                clauses.append(f"IDCliente IN ({ph(client_ids)})")
                params.extend(client_ids)
            if operation_ids:
                clauses.append(f"IDOperacion IN ({ph(operation_ids)})")
                params.extend(operation_ids)
            rows = self.qall(
                "SELECT IDRegistroAlerta FROM tbAlertas WHERE " + " OR ".join(clauses),
                params,
            )
            alert_ids = [int(r["IDRegistroAlerta"]) for r in rows]

        if alert_ids:
            marks = ph(alert_ids)
            self.execute(f"DELETE FROM tbPagosAlertas WHERE IDRegistroAlerta IN ({marks})", alert_ids)
            self.execute(f"DELETE FROM tbReporteRegulatorioPLD WHERE IDRegistroAlerta IN ({marks})", alert_ids)
            self.execute(f"DELETE FROM tbAlertas WHERE IDRegistroAlerta IN ({marks})", alert_ids)

        log_params: list[Any] = []
        log_clauses = []
        if client_ids:
            log_clauses.append(f"IDCliente IN ({ph(client_ids)})")
            log_params.extend(client_ids)
        if operation_ids:
            log_clauses.append(f"IDOperacion IN ({ph(operation_ids)})")
            log_params.extend(operation_ids)
        log_clauses.append("CuerpoHtml LIKE %s")
        log_params.append(f"%{self.run_token}%")
        self.execute("DELETE FROM logCorreos WHERE " + " OR ".join(log_clauses), log_params)

        if operation_ids:
            marks = ph(operation_ids)
            self.execute(f"DELETE FROM tbOperacionesPagos WHERE IDOperacion IN ({marks})", operation_ids)
            self.execute(f"DELETE FROM tbOperaciones WHERE IDOperacion IN ({marks})", operation_ids)

        if client_ids:
            marks = ph(client_ids)
            self.execute(f"DELETE FROM tbPerfilTransaccional WHERE IDCliente IN ({marks})", client_ids)
            self.execute(f"DELETE FROM tbClientesPPE WHERE IDCliente IN ({marks})", client_ids)
            self.execute(f"DELETE FROM logDetectClientesListas WHERE IDCliente IN ({marks})", client_ids)
            self.execute(f"DELETE FROM logClientesDomicilio WHERE IDCliente IN ({marks})", client_ids)
            self.execute(f"DELETE FROM logClientes WHERE IDCliente IN ({marks})", client_ids)
            self.execute(f"DELETE FROM tbClientesDomicilio WHERE IDCliente IN ({marks})", client_ids)
            self.execute(f"DELETE FROM tbClientes WHERE IDCliente IN ({marks})", client_ids)

        if list_ids:
            marks = ph(list_ids)
            self.execute(f"DELETE FROM tbListasNegraCNSF WHERE IDRegistroListaCNSF IN ({marks})", list_ids)

        print(
            f"  Eliminados: {len(client_ids)} clientes, {len(operation_ids)} operaciones, "
            f"{len(alert_ids)} alertas, {len(list_ids)} registros de lista negra."
        )

    def print_summary(self) -> None:
        print("\n=== RESUMEN ===")
        total = len(self.results)
        ok_count = sum(1 for r in self.results if r["ok"])
        failed = [r for r in self.results if not r["ok"]]

        for result in self.results:
            mark = "OK" if result["ok"] else "ERROR"
            print(f"[{mark}] {result['caso']} | {result['verificacion']} | {result['detalle']}")

        print(f"\nVerificaciones: {total} | OK: {ok_count} | Errores: {len(failed)}")
        if failed:
            print("\nFallos:")
            for result in failed:
                print(f"  - {result['caso']} / {result['verificacion']}: {result['detalle']}")

    def run(self) -> int:
        print("=== GENERADOR DE PRUEBAS PLD ===")
        print(f"  Prefijo único: {self.prefix}-{self.run_token}")

        self.load_catalogs()
        self.check_official_config()
        self.check_api_reachable()

        log_row = self.q1("SELECT COALESCE(MAX(id), 0) AS max_id FROM logCorreos")
        alert_row = self.q1("SELECT COALESCE(MAX(IDRegistroAlerta), 0) AS max_id FROM tbAlertas")
        self.initial_max_log_id = int(log_row["max_id"] if log_row else 0)
        self.initial_max_alert_id = int(alert_row["max_id"] if alert_row else 0)

        if self.args.dry_run:
            self.print_dry_run()
            return 0

        self.confirm()
        self.login()

        scenarios = [
            ("Actividad vulnerable", self.scenario_actividad_vulnerable),
            ("Zona geografica (sin alerta)", self.scenario_zona_geografica),
            ("Personas Bloqueadas", self.scenario_personas_bloqueadas),
            ("PPE", self.scenario_ppe_operacion),
            ("Perfil transaccional >= 2 (sin alerta)", self.scenario_perfil_transaccional),
            ("Ubicación de riesgo + prima >= parámetro 14 (sin alerta)", self.scenario_ubicacion_prima),
            ("Fraccionado", self.scenario_fraccionado),
            ("Acumulado Efectivo", self.scenario_acumulado_efectivo),
            ("Monto", self.scenario_monto),
            ("Monto Inusual", self.scenario_monto_usual),
            ("Cancelacion", self.scenario_cancelacion),
        ]

        for name, scenario in scenarios:
            print(f"\n--- {name} ---")
            try:
                scenario()
            except Exception as exc:
                print(f"  [ERROR] El escenario falló: {exc}")
                self.add_result(name, "escenario completo", False, str(exc))

        if self.args.limpiar:
            try:
                self.cleanup()
            except Exception as exc:
                print(f"  [ERROR] No se pudo completar la limpieza: {exc}")

        self.print_summary()
        return 0 if all(result["ok"] for result in self.results) else 1


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    if args.insecure:
        try:
            import urllib3

            urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        except Exception:
            pass

    try:
        seeder = PLDSeeder(args)
        return seeder.run()
    except SeedError as exc:
        print(f"\nERROR: {exc}", file=sys.stderr)
        return 2
    except KeyboardInterrupt:
        print("\nEjecución interrumpida.", file=sys.stderr)
        return 130
    except Exception as exc:
        print(f"\nERROR inesperado: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
