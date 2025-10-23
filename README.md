## Instrucciones para iniciar un proyecto Laravel recién clonado

1. **Clona el repositorio**

   ```bash
   git clone <URL_DEL_REPOSITORIO>
   cd <NOMBRE_DEL_PROYECTO>
   ```

2. **Instala las dependencias de Composer**

   ```bash
   composer install
   ```

3. **Copia y configura el archivo de entorno**

   ```bash
   cp .env.example .env
   ```

   Luego edita el archivo `.env` con la configuración de tu base de datos y demás variables de entorno necesarias.

4. **Genera la clave de la aplicación**

   ```bash
   php artisan key:generate
   ```

5. **Ejecuta las migraciones de la base de datos**

   ```bash
   php artisan migrate
   ```

   Si tienes datos de prueba (seeders), ejecuta también:

   ```bash
   php artisan db:seed
   ```

6. **Instala las dependencias de NPM y compila los assets (si corresponde)**

   ```bash
   npm install
   npm run dev
   ```

7. **(Opcional) Configura permisos de escritura para almacenamiento y caché**

   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

8. **Levanta el servidor de desarrollo**

   ```bash
   php artisan serve
   ```

   El proyecto estará disponible en [http://localhost:8000](http://localhost:8000).

---

¡Listo! El proyecto debería estar funcionando localmente.
