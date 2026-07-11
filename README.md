# ⚙️ Prueba Técnica - API RESTful (Backend)

![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Database](https://img.shields.io/badge/Database-MongoDB_/_NoSQL-47A248?style=for-the-badge&logo=mongodb&logoColor=white)

Servicio de API RESTful desarrollado en **Laravel** diseñado para operar como el motor lógico y transaccional de la Prueba Técnica. El sistema interactúa directamente con una base de datos **NoSQL** e implementa capas avanzadas de middleware para la segregación de privilegios y auditoría de eventos de manera robusta.

---

## 🚀 Características Clave y Arquitectura

* **Arquitectura RESTful Sólida:** Exposición de endpoints limpios y estandarizados mapeados bajo controladores dedicados que devuelven respuestas JSON homogéneas.
* **Seguridad Granular y Principio de Mínimo Privilegio:** Implementación de un flujo de enrutamiento desacoplado en `api.php`. Permite a los usuarios autenticados consultar y actualizar su propio perfil de forma segura (`TokenMongodbMiddleware`) mientras mantiene restringidas las operaciones de administración masiva (`validar.seccion:usuarios`).
* **Bitácora de Auditoría Integrada:** Middleware integrado encargado de registrar cronológicamente en colecciones NoSQL cada una de las mutaciones (`store`, `update`, `destroy`) sobre entidades críticas del sistema.
* **Spoofing de Métodos:** Soporte nativo para cargas complejas de archivos binarios/imágenes combinadas con verbos HTTP asíncronos mediante el spoofing de parámetros (`_method: PUT`).

---

## 🛠️ Requisitos Previos

Asegúrate de cumplir con los siguientes requisitos en tu servidor o entorno local:
* **PHP:** Versión 8.2 o superior.
* **Composer:** Manejador de dependencias de PHP.
* **Base de datos:** Instancia activa de MongoDB local.
* **Extensión de PHP:** Driver nativo de MongoDB instalado y habilitado en tu archivo `php.ini`.
    * **URL para DLL de MongoDB:** https://pecl.php.net/package/mongodb/1.16.2/windows
---
## 📦 Guía Rápida de Instalación y Ejecución

Ejecuta la siguiente secuencia completa de comandos en tu terminal para clonar, configurar e inicializar el entorno de la API:

```bash
# 1. Clonar el repositorio del backend
git clone https://github.com/ingpenriquez77/api-backend.git

# 2. Acceder al directorio del proyecto
cd api-backend

# 3. Instalar las dependencias de PHP mediante Composer
composer install

# 4. Crear el archivo de configuración del entorno desde la plantilla base
cp .env.example .env

# 5. Generar la llave criptográfica única de la aplicación Laravel
php artisan key:generate

# [!IMPORTANT]
Paso previo indispensable: Antes de continuar con las migraciones y seeders, debes abrir tu gestor de bases de datos de MongoDB (MongoDB Compass, Mongo Shell, etc.) y crear una base de datos vacía con el nombre prueba_tecnica.

Posteriormente, abre el archivo .env que acabas de generar en la raíz de tu proyecto del backend y asegúrate de configurar el bloque de conexión con los siguientes valores exactos:

# Fragmento de código
DB_CONNECTION=mongodb
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=prueba_tecnica

# 6. Ejecutar migraciones y carga de datos iniciales en MongoDB
php artisan migrate --seed

# 7. Levantar el servidor de desarrollo local de Laravel
php artisan serve
