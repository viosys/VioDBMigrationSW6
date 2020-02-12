# VioDBMigration

### Usage:

add
`VIOSYS_MIGRATION_NAMESPACE='VioDbMigration'`
`VIOSYS_MIGRATION_DIRECTORY='vio_sql/migrations'`
to .env-file

install plugin:

`` bin/console plugin:install --activate VioDBMigration``

create an migration under the configured directory:

``bin/console database:create-migration vio_sql/migrations/ VioDbMigration``


run migrations with:

``bin/console database:migrate --all VioDbMigration``

