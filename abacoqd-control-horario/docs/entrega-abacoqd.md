# Preparación de la entrega de AbacoQD

La configuración normal corresponde a una única sede **Principal, Madrid**, confirmada para esta entrega. El catálogo municipal aporta el código INE 28079, provincia y comunidad. No se crean personas, horarios, vacaciones, registros de jornada ni contraseñas de demostración durante una instalación normal. Esta guía cubre la preparación del repositorio dedicado a AbacoQD y su instalación.

## Instalación nueva desde el código

Requisitos: PHP compatible con `composer.lock` y sus extensiones, Composer, Node.js y npm. Se conservan `composer.lock` y `package-lock.json`; instalar desde esos archivos, sin actualizar dependencias durante el despliegue.

1. Copiar `.env.example` a `.env`. Mantener `APP_DEMO=false`; en producción utilizar `APP_ENV=production`, `APP_DEBUG=false`, HTTPS y una base de datos propia. No copiar una base de pruebas al servidor.
2. Ejecutar `composer install` para desarrollo, o `composer install --no-dev --optimize-autoloader` para el entorno final.
3. Ejecutar `php artisan key:generate`. Conservar después esa clave en la configuración privada: cambiarla invalida las sesiones y los datos cifrados.
4. Crear el archivo SQLite vacío si se utiliza SQLite, o configurar la conexión prevista. Ejecutar `php artisan migrate` y `php artisan db:seed`; en producción los comandos de migración/sembrado necesitan `--force` tras comprobar la base de destino.
5. Crear el acceso mediante `php artisan abaco:crear-administrador correo@dominio.es "Administración"`. La clave debe tener al menos doce caracteres y se pide dos veces de forma oculta. No existe una clave inicial pública ni una opción de contraseña por línea de comandos. Con varias sedes, indicar `--centro=ID`.
6. La cuenta `jefa` gestiona personas, horarios y calendarios. Para la operación técnica, crear una cuenta distinta con el mismo comando y `--rol=tecnico`. El comando rechaza correos existentes y nunca cambia su clave ni sus permisos.
7. Ejecutar `npm ci --ignore-scripts` y `npm run build`. El servidor web debe servir únicamente la carpeta `public/`, con permiso de escritura limitado a `storage/` y `bootstrap/cache/`.
8. Configurar el calendario, horarios y fechas de incorporación reales antes de activar los avisos. La cuenta administrativa no recibe un horario ficticio por crearse.

`MAIL_MAILER=log` no envía correo, pero guarda el contenido y los enlaces de recuperación en registros privados. Configurar SMTP antes de utilizar la recuperación real, limitar acceso a los registros y definir su retención. No publicar archivos `.env`, bases, copias o exportaciones. Las sesiones usan cifrado en el archivo de ejemplo.

## Demostración separada

Utilizar otra carpeta o base de datos, sin datos reales. La demostración sólo se habilita en `local` o `testing`:

```sh
# En el .env de esa instalación separada: APP_ENV=local y APP_DEMO=true
php artisan db:seed --class=DemoSeeder
```

Este comando explícito carga las sedes y personas sintéticas de `database/seeders/data/personas.json`. Sus correos utilizan el dominio reservado `abaco.test`; la pantalla de acceso sólo muestra las credenciales ficticias cuando se activa la bandera de demostración. El importador es de sólo lectura y no exporta altas al repositorio. Sembrar de nuevo no modifica cuentas que ya existían. La demostración está bloqueada en producción incluso con la bandera activada.

Las pruebas que necesitan las diecinueve sedes antiguas las reciben expresamente mediante `CentrosDemoSeeder`. No se crean empleados de ejemplo de forma global en las pruebas. Las pruebas de instalación usan una base efímera propia para comprobar el arranque vacío.

## Conversión de una instalación de pruebas existente

No ejecutar `migrate:fresh` ni borrar registros sobre una instalación con actividad real. El seeder normal no limpia instalaciones anteriores y el cambio de la migración histórica sólo evita insertar sedes vacías en instalaciones nuevas; conserva las correspondencias necesarias al migrar datos heredados.

Para esta copia local de revisión: detener servidor, colas y planificador; guardar fuera del repositorio una copia íntegra verificada de la base y de la configuración privada; preparar **otra base SQLite vacía**; migrarla y ejecutar el seeder normal; crear el acceso de revisión con un secreto nuevo; cambiar la conexión a esa nueva base; comprobar acceso, sede, calendario y ausencia de actividad ficticia antes de volver a arrancar. Mantener la base anterior intacta permite revertir cambiando la conexión. No es necesario eliminar el programa ni sus archivos.

## Revisión antes de compartir

- Ejecutar las pruebas, el formateador y la construcción del frontend. La batería utiliza SQLite en memoria y correo simulado, sin datos de la instalación local.
- Revisar `git status` y `git diff --cached` antes de cualquier futuro commit. `.gitignore` no elimina secretos que ya estuvieran dentro del historial; si apareciera alguno, detener la publicación y rotarlo antes de sanearlo.
- Mantener datos públicos del INE, referencias de boletines y fixtures sintéticos. Las bases, altas, adjuntos, correos, sesiones, copias, resultados de interfaz y notas privadas quedan excluidos. No colocar exportaciones personales en carpetas de código.
- Los archivos de distribución generados por Git conservan las pruebas y las guías de instalación. `.gitattributes` excluye únicamente los archivos internos enumerados con `export-ignore`. Un archivo de código no sustituye la instalación de dependencias y la construcción de recursos.
- Antes de publicar, comprobar que el remoto corresponde al repositorio dedicado a AbacoQD y revisar los archivos incluidos. Registrar su dirección y las comprobaciones de integración continua cuando estén verificadas.

## Parche de seguridad aplicado

Auditoría del 30/10/2026: `composer audit --locked --format=json` identifica una vulnerabilidad media en `symfony/mime` **v7.4.19**, CVE-2026-45070 / PKSA-wtxr-p26d-nn42. Afecta a nombres de parámetros de cabeceras MIME que procedan de entradas no confiables. La fuente primaria confirma el parche en **v7.4.22**: [aviso de Symfony](https://symfony.com/cve-2026-45070).

La simulación confirmó que la actualización quedaba limitada a ese paquete dentro de la rama 7.4, sin cambiar otras dependencias:

```sh
composer update symfony/mime:7.4.22 --dry-run --no-scripts --no-interaction
```

Tras la aprobación del parche, se ejecutó la operación sin `--dry-run`: `composer.lock` y la instalación pasan de **v7.4.19 a v7.4.22**, sin cambios en `composer.json` ni en otros paquetes. La auditoría posterior devuelve `{"advisories":[],"abandoned":[]}`. Las pruebas de recuperación de contraseña, avisos por correo, arranque, separación demo y altas pasan conjuntamente: **43 pruebas, 191 aserciones**. La actualización no envía correos reales.

No usar una actualización general: la simulación sin fijar versión proponía un salto a Symfony MIME 8.1. Este parche concreto ya está aplicado. Las demás actualizaciones de dependencias se revisan por separado de la publicación del repositorio.
