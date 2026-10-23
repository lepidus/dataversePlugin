# Manual core version repair / Reparo manual / Reparación manual

## English

`RepairCoreVersionMigration` repairs only the incorrect core **3.4.6.2** entry from [PKP #13608](https://github.com/pkp/pkp-lib/issues/13608). It is an explicit CLI operation, not registered in the plugin installation/upgrade descriptors. It supports OJS (`ojs2`) and OPS (`ops`) 3.4 with MySQL/MariaDB (InnoDB `versions` table) or PostgreSQL.

Back up the database outside application/public/files directories, prevent concurrent upgrades, and confirm that **no application upgrade was attempted after the incident**. Failed upgrades can modify the schema without recording a version; this repair cannot detect or recover that situation. Run from the application root with its PHP runtime, **before replacing the core code for a normal upgrade**.

The XML at `dbscripts/xml/version.xml` must identify this application as `core`, version `3.4.0.x`, exactly matching the unique immediately preceding inactive core record. The only active and last core record must be `3.4.6.2`. Its Dataverse record must immediately follow by `version_id`, with the same installation timestamp and expected plugin metadata. Histories without this exact signature are refused, including other affected installations.

First inspect (the default mode changes nothing):

```sh
php lib/pkp/tools/migration.php 'APP\plugins\generic\dataverse\classes\migrations\RepairCoreVersionMigration' up
```

After reviewing the proposed records and confirming the prerequisites, apply explicitly:

```sh
DATAVERSE_CORE_VERSION_REPAIR=apply php lib/pkp/tools/migration.php 'APP\plugins\generic\dataverse\classes\migrations\RepairCoreVersionMigration' up
```

The transaction deletes only the confirmed incorrect core row, reactivates the row matching the code, and verifies the result. Plugin/other-application records remain intact. Repeating a completed repair changes nothing. Save the command output with maintenance records outside web-served paths. `down` is unsupported; recovery uses the backup. This does not replace the preventive core fix or repair other incorrect versions.

## Português

`RepairCoreVersionMigration` repara somente o registro incorreto core **3.4.6.2** de [PKP #13608](https://github.com/pkp/pkp-lib/issues/13608). É uma operação explícita pela CLI, não registrada nos descritores de instalação/atualização do plugin. Suporta OJS (`ojs2`) e OPS (`ops`) 3.4 com MySQL/MariaDB (tabela `versions` InnoDB) ou PostgreSQL.

Faça backup do banco fora dos diretórios da aplicação/public/files, impeça upgrades concorrentes e confirme que **nenhum upgrade da aplicação foi tentado após o incidente**. Um upgrade que falha pode mudar o esquema sem registrar uma versão; este reparo não detecta nem recupera essa situação. Execute na raiz da aplicação, com o PHP dela, **antes de substituir o código do núcleo para um upgrade normal**.

O XML em `dbscripts/xml/version.xml` deve identificar esta aplicação como `core`, versão `3.4.0.x`, coincidindo exatamente com o único registro inativo do núcleo imediatamente anterior. O único núcleo ativo e último do histórico deve ser `3.4.6.2`. O registro do Dataverse deve vir imediatamente depois por `version_id`, com a mesma data/hora de instalação e os metadados esperados do plugin. Históricos sem essa assinatura exata são recusados, inclusive outras instalações afetadas.

Primeiro confira (o modo padrão não altera nada):

```sh
php lib/pkp/tools/migration.php 'APP\plugins\generic\dataverse\classes\migrations\RepairCoreVersionMigration' up
```

Após revisar os registros propostos e confirmar os pré-requisitos, aplique explicitamente:

```sh
DATAVERSE_CORE_VERSION_REPAIR=apply php lib/pkp/tools/migration.php 'APP\plugins\generic\dataverse\classes\migrations\RepairCoreVersionMigration' up
```

A transação remove somente a linha incorreta confirmada, reativa a linha correspondente ao código e confere o resultado. Registros de plugins/outras aplicações são preservados. Repetir o reparo concluído não altera nada. Salve a saída nos registros de manutenção fora de caminhos servidos pela web. `down` é indisponível; a recuperação usa o backup. Isso não substitui a correção preventiva do núcleo nem repara outras versões incorretas.

## Español

`RepairCoreVersionMigration` repara solamente el registro incorrecto core **3.4.6.2** de [PKP #13608](https://github.com/pkp/pkp-lib/issues/13608). Es una operación explícita por CLI, no registrada en los descriptores de instalación/actualización del plugin. Admite OJS (`ojs2`) y OPS (`ops`) 3.4 con MySQL/MariaDB (tabla `versions` InnoDB) o PostgreSQL.

Respalde la base de datos fuera de los directorios de la aplicación/public/files, impida actualizaciones concurrentes y confirme que **no se intentó actualizar la aplicación después del incidente**. Una actualización fallida puede cambiar el esquema sin registrar una versión; esta reparación no detecta ni recupera esa situación. Ejecute desde la raíz de la aplicación con su PHP, **antes de sustituir el código del núcleo para una actualización normal**.

El XML de `dbscripts/xml/version.xml` debe identificar esta aplicación como `core`, versión `3.4.0.x`, coincidiendo exactamente con el único registro inactivo del núcleo inmediatamente anterior. El único núcleo activo y último del historial debe ser `3.4.6.2`. El registro de Dataverse debe seguirlo inmediatamente por `version_id`, con la misma fecha/hora de instalación y los metadatos esperados del plugin. Se rechazan los historiales sin esa firma exacta, incluidas otras instalaciones afectadas.

Primero compruebe (el modo predeterminado no modifica nada):

```sh
php lib/pkp/tools/migration.php 'APP\plugins\generic\dataverse\classes\migrations\RepairCoreVersionMigration' up
```

Después de revisar los registros propuestos y confirmar los requisitos, aplique explícitamente:

```sh
DATAVERSE_CORE_VERSION_REPAIR=apply php lib/pkp/tools/migration.php 'APP\plugins\generic\dataverse\classes\migrations\RepairCoreVersionMigration' up
```

La transacción elimina solamente la fila incorrecta confirmada, reactiva la fila correspondiente al código y verifica el resultado. Conserva registros de plugins/otras aplicaciones. Repetir la reparación completada no modifica nada. Guarde la salida en los registros de mantenimiento fuera de rutas públicas. `down` no está disponible; la recuperación utiliza el respaldo. Esto no sustituye la corrección preventiva del núcleo ni repara otras versiones incorrectas.
