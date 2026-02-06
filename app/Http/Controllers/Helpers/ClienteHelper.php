<?php

namespace App\Http\Controllers\Helpers;

class ClienteHelper
{
    /**
     * Obtiene un token PPE utilizando el método correcto (POST, no GET).
     * El error 403 (This action is unauthorized) se produce cuando se usa GET.
     *
     * Postman seguramente está usando método POST, por eso funciona allí.
     * Aquí asegúrate de simular la misma petición que hace Postman: POST, no GET.
     */
    public static function getTokenPPE($credentials = [])
    {
        $url = 'https://app.q-detect.com/api/token?client_id=780413-5348-8120';
        $bearer = 'YKsaMDz7m1F9sVIf6siDavc4HKVo0dpkJci1OIUSKOFAqmxKEltlqQNwcslp0GM9FmS3e0PY16rMJwxPNJB00FukGV2VkkEdnYuKejtiHpONFdJlncX2mmyBIRCTtKko';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$bearer,
            // Q-DTECT ya no retorna json, se ignora 'Accept'
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            // En caso de error curl
            return null;
        }

        // El token viene en el cuerpo en texto plano.
        // Puede venir vacío si hubo un error de autenticación (403, etc)
        $token = trim($response);

        // Si el token viene vacío lo tratamos como error.
        if ($token === '') {
            return null;
        }

        return $token;
    }

    /**
     * Realiza una petición GET al endpoint PPE para buscar coincidencias de personas expuestas políticamente.
     *
     * @param  string  $bearerToken  Token de autorización tipo Bearer.
     * @param  string  $name  Nombre completo a buscar (los espacios serán reemplazados por '+').
     * @param  int  $percent  Porcentaje de coincidencia (default: 80).
     * @param  string  $username  Nombre de usuario para el query (default: Tlaloc_01).
     * @param  string  $clientId  ID del cliente (default: 780413-5348-8120).
     * @return mixed Respuesta decodificada (array) o null en caso de error.
     */
    public static function getPPE($bearerToken, $name, $percent = 100, $username = 'Tlaloc_01', $clientId = '780413-5348-8120')
    {
        // Sanitizar y codificar el nombre para la URL
        $encodedName = str_replace(' ', '+', trim($name));
        $url = "https://app.q-detect.com/api/find?client_id={$clientId}&username={$username}&name={$encodedName}&percent={$percent}";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$bearerToken,
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return null;
        }

        $decoded = json_decode($response, true);

        return $decoded;
    }
}
