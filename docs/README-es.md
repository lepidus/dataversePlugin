**Español** | [English](/README.md) | [Português Brasileiro](/docs/README-pt_BR.md)

# Módulo Dataverse

Este plugin es el resultado de una colaboración entre SciELO Brasil y Lepidus. Permite integrar Open Journal Systems (OJS) y Open Preprint Systems (OPS) con un repositorio Dataverse.

De este modo, los autores pueden enviar los datos de investigación asociados a sus manuscritos durante el proceso de envío a la revista o al servidor de preprints. Los datos de investigación permanecen disponibles en el flujo editorial (por ejemplo, pueden estar disponibles durante la evaluación por pares del artículo o la moderación del preprint) y se vinculan a la publicación en OJS/OPS.

Para comprender mejor la instalación y el uso del módulo, vea nuestro vídeo institucional: [Módulo Dataverse: Introducción y tutorial](https://app.heygen.com/videos/dataverse-plugin-introduction-and-tutorial-3cede08c38bf4065a4f769c1e35ae71d-en?languageCode=es).

## Compatibilidad

Este plugin es compatible con las siguientes aplicaciones PKP:

- OPS y OJS en las versiones 3.3 y 3.4.

Consulte la última versión compatible con su aplicación en la [Página de Versiones](https://github.com/lepidus/dataversePlugin/releases).

## Requisitos para uso

1. **app_key**

La instancia de OJS/OPS debe tener la clave de la aplicación (`app_key`, en la sección `[general]` de `config.inc.php`) configurada. La instalación y la actualización a la 3.5 la crean; si falta, su administrador del sistema puede generarla con `php lib/pkp/tools/appKey.php generate`.

Esto es necesario para utilizar las credenciales de API proporcionadas, que se almacenan cifradas en la base de datos. Cambiar la clave después hace ilegibles las credenciales almacenadas, que deben introducirse nuevamente en la configuración del plugin.

## Instalación

### Instrucciones

Este módulo está disponible para su instalación a través de la [Galería de Plugins PKP](https://docs.pkp.sfu.ca/plugin-inventory/en/). Para instalarlo, siga estos pasos:

1. Acceda a la zona __Panel de control__ de su sitio.
2. Navegue hasta `Ajustes` > `Sítio web` > `Módulos` > `Galería de módulos`.
3. Busque el módulo llamado `Módulo Dataverse` y haga clic en su nombre.
4. En la ventana que se abre, haga clic en `Instalar` y confirme que desea instalar el módulo.

Siguiendo estos pasos, el módulo se instalará en tu OJS/OPS. Después de la instalación, cuando quieras comprobar si hay una nueva versión disponible, sólo tienes que seguir la misma ruta y comprobar el estado del módulo en la lista.

## Instrucciones de uso

### Configuración
Después de la instalación, necesitas habilitar el módulo. Esto se hace en `Ajustes`> `Sitio web`> `Módulos`> `Módulos instalados`.

Con el módulo habilitado, debe ampliar sus opciones haciendo clic en la flecha situada junto al nombre del módulo y, a continuación, haciendo clic en `Ajustes`.

En la nueva ventana aparecerán las opciones _Dataverse URL_, _Token de API_, _Términos de uso_ y _Instrucciones Adicionales_.

Debe introducir la URL completa del repositorio Dataverse donde se depositarán los datos de la búsqueda. Por ejemplo: `https://demo.dataverse.org/dataverse/anotherdemo`.

Las condiciones de uso pueden definirse para cada idioma configurado en su aplicación. Si tiene dudas sobre cuáles son los términos, consulte al responsable de su repositorio.

**Importante**: El `Token de API` pertenece a una cuenta de usuario de Dataverse. Para más información sobre cómo obtener el token de API, consulte la [Guía del usuario de Dataverse](https://guides.dataverse.org/en/5.13/user/account.html#api-token).

Es importante mencionar que la cuenta de usuario de Dataverse se incluirá en la lista de contribuyentes de los conjuntos de datos depositados a través del plugin (para más información, consulta [esta discusión](https://groups.google.com/g/dataverse-community/c/Oo4AUZJf4hE/m/DyVsQq9mAQAJ)).

Por lo tanto, se recomienda crear un usuario específico para la revista o servidor de preprints, en lugar de utilizar una cuenta personal, ya que cada depósito estará asociado con esta cuenta.

Después de completar los campos, solo confirma la acción haciendo clic en `Guardar`. El plugin funcionará solo después de completar esta configuración.

#### Límites de tiempo de las solicitudes
Las conexiones con Dataverse tienen un límite de 5 segundos, y cada solicitud tiene un límite total que depende de la operación: 15 segundos para lecturas, 60 segundos para la descarga de cada archivo, 30 segundos para la creación del dataset, 60 segundos para la carga de cada archivo y 30 segundos para los demás cambios (incluidas las eliminaciones). Los administradores pueden cambiar estos límites en el `config.inc.php` de la aplicación:

```ini
[dataverse]
read_timeout = 15
download_timeout = 60
create_timeout = 30
upload_timeout = 60
write_timeout = 30
```

Los valores deben ser números enteros de segundos entre 1 y 300, escritos sin cero a la izquierda (la aplicación lee `060` como un número octal); cualquier otro valor, como `on` o `1.5`, usa el predeterminado. Los archivos se depositan uno tras otro en la misma solicitud, por lo que, antes de aumentar un límite, verifique que los límites de solicitud de PHP y del servidor web cubran el tiempo de todas las cargas juntas. Una solicitud que alcanza su límite se informa como tiempo agotado: Dataverse puede haber completado la operación de todos modos.

### Uso

Se añade una sección llamada "Datos de investigación" al paso "Archivos" durante el proceso de envío. Además, los metadatos del conjunto de datos deben completarse en el paso "Para los editores/as".

Cuando el plugin crea un conjunto de datos, completa la Fecha de depósito (Deposit Date) de sus metadatos de cita con el día del depósito; editar los metadatos después conserva esa fecha. Nunca se pide a los autores, aunque la colección la marque como obligatoria.

Los autores, moderadores, editores o gerentes también pueden editar el conjunto de datos antes de su publicación, en el ítem "Datos de investigación" añadido a la sección Dataverse del menú del flujo de trabajo del envío. Junto a él se añade un ítem "Declaración de datos", para que la declaración de disponibilidad de datos pueda ser consultada y modificada durante el flujo de trabajo editorial.

La cita del conjunto de datos y sus indicadores de estado aparecen en el encabezado de Datos de investigación, con el mismo estilo de estado usado en Publicación. Los archivos se muestran encima del formulario de metadatos, sin pestañas.

Antes de cargar cada archivo, el plugin espera hasta 30 segundos a que Dataverse termine de procesar (ingest) los archivos que ya están en el conjunto de datos. Si Dataverse todavía los está procesando después de ese tiempo, el archivo no se carga y un error pide intentarlo de nuevo en unos minutos; cuando esto ocurre durante un depósito, el conjunto de datos y los archivos ya enviados se conservan, y un nuevo depósito continúa desde el archivo que no se envió.

En OJS, los evaluadores pueden recibir acceso a los archivos de datos de investigación durante el proceso de evaluación. El acceso de los evaluadores a estos archivos puede ser restringido en la Configuración del Flujo de Trabajo, para que solo visualicen los archivos cuando acepten evaluar la presentación.

#### Depósitos interrumpidos
El plugin registra cada etapa del depósito antes de llamar a Dataverse. Si un depósito se interrumpe después de que el conjunto de datos fue creado, un nuevo intento reutiliza el mismo conjunto de datos: envía de nuevo los metadatos, de modo que se conservan los cambios hechos entre tanto, y envía solo los archivos que aún no fueron confirmados. Si los archivos de datos de investigación cambiaron entre tanto, el conjunto de datos parcial se elimina y el depósito empieza de nuevo con la nueva selección. Cuando Dataverse rechaza una etapa (una licencia inválida o un token vencido, por ejemplo), no se cambió nada allí, se muestra el error y el depósito puede intentarse de nuevo una vez corregida la causa. Cuando el resultado de una etapa es desconocido — la solicitud alcanzó el límite de tiempo o el proceso se detuvo durante la creación del conjunto de datos o el envío de un archivo —, los nuevos intentos para ese envío se rechazan con un mensaje que pide contactar con el equipo editorial, para que el plugin nunca cree un conjunto de datos duplicado ni envíe un archivo dos veces.

Estos depósitos deben ser conciliados por un administrador, directamente en la base de datos, solo para el envío afectado:

1. Asegúrese de que ninguna solicitud del envío siga en ejecución y haga una copia de seguridad de sus filas en `dataverse_deposits`, `dataverse_studies` y `draft_dataset_files`.
2. En el estado `creating`, verifique en Dataverse si el conjunto de datos fue creado. Si lo fue, guarde su identificador persistente en `persistent_id` y siga al paso 4. Si Dataverse demuestra que no se creó nada, cambie el estado a `retired` e incremente `revision`, restringido al `submission_id`, al `state` y a la `revision` inspeccionados: el próximo depósito empieza desde el principio.
3. En el estado `uploading`, verifique qué archivos del manifiesto llegaron al conjunto de datos y registre sus ids de Dataverse en `receipts`; un archivo que no llegó a Dataverse queda sin recibo.
4. Con `persistent_id` completado, en una única transacción restringida al `submission_id`, al `state` y a la `revision` inspeccionados, cambie el estado a `ready` e incremente `revision`; después, intente el depósito de nuevo desde el panel "Datos de investigación".
5. Si la URL de Dataverse cambió después de crear el conjunto de datos, el depósito no puede continuar en la nueva colección: conserve o elimine el conjunto de datos en la anterior según sea necesario, y después cambie el estado a `retired` e incremente `revision`.

## Instrucciones para desarrollo:
1. Clona el repositorio del plugin Dataverse.
2. Para utilizar el plugin en una aplicación PKP, copia su directorio en el directorio `/plugins/generic`, asegurándote de que el directorio se llame `dataverse`.
3. Desde la raíz del directorio de la aplicación PKP, ejecuta el siguiente comando para actualizar la base de datos, creando las tablas utilizadas por el plugin:
    - `php tools/upgrade.php upgrade`

## Ejecución de pruebas

Las pruebas que se comunican con Dataverse usan una colección Dataverse real. Exporta sus credenciales antes de ejecutarlas:

```
export DATAVERSE_URL="https://demo.dataverse.org/dataverse/myDataverseAlias"
export DATAVERSE_API_TOKEN="abcd-abcd-abcd-abcd-abcdefghijkl"
export DATAVERSE_TERMS_OF_USE="https://dataverse.org/best-practices/harvard-dataverse-general-terms-use"
export DATAVERSE_CUSTOM_REQUIRED_METADATA_URL="https://demo.dataverse.org/dataverse/customRequiredMetadataFields"
```

`DATAVERSE_CUSTOM_REQUIRED_METADATA_URL` apunta a una colección, en el mismo servidor y accesible con el mismo token, cuyos bloques de metadatos exigen `alternativeURL`, `dsDescriptionDate`, `PSRI1` y `PSRI2`; la usan los escenarios de metadatos exigidos por la colección. Sin estas variables, las pruebas PHPUnit que las necesitan se omiten localmente y fallan en la CI, y la suite de Playwright no se inicia.

### Pruebas unitarias y de integración (PHPUnit)

Ejecuta desde la raíz del directorio de tu aplicación PKP:
```
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit.xml plugins/generic/dataverse/tests
```

Los escenarios de configuración, de envío, del flujo de trabajo y de la página pública, incluidos los metadatos exigidos por una colección, se ejecutan contra la base de datos de la aplicación, archivos reales y la colección Dataverse anterior, sin mocks.

### Pruebas de extremo a extremo (Playwright)

Aquí solo se prueba el comportamiento que existe únicamente en el navegador: la validación del formulario de configuración hecha en el navegador, los campos y secciones condicionales del asistente de envío, el modal de carga de datos de investigación, el paso de revisión, los paneles Datos de investigación y Declaración de datos del flujo de trabajo, los pasos de las decisiones editoriales, los datos de investigación mostrados a los evaluadores y la página pública. Los escenarios de publicación y de aceptación publican un dataset en la colección de prueba en cada ejecución, y Dataverse no permite que un token de API común elimine un dataset publicado, así que usa una colección destinada a pruebas.

La aplicación debe estar en ejecución con el conjunto de datos de prueba de referencia (contexto `publicknowledge`, usuarios `dbarnes`, `eostrom` y, en OJS, `jjanssen`) y el idioma `en`. En el directorio del plugin:
```
npm install
npx playwright install chromium
npm run test:e2e
```

`BASE_URL` apunta a la aplicación (por defecto `http://localhost:8000`) y `APP_ROOT` a su directorio raíz (por defecto: tres niveles por encima del plugin). Para ejecutarlas en OPS, define ambas, por ejemplo `APP_ROOT=/ruta/a/ops BASE_URL=http://localhost:8001 npm run test:e2e`.

## Créditos

Este plugin fue patrocinado por la Scientific Electronic Library Online (SciELO) y desarrollado por Lepidus Tecnologia.

El desarrollo de este plugin pretende dar continuidad a la integración entre OJS y Dataverse, realizada anteriormente a través de [módulo para OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## Licencia

__Este plugin está licenciado bajo la GNU General Public Licence v3.0__

__Copyright (c) 2021-2025 Lepidus Tecnologia__

__Copyright (c) 2021-2025 SciELO__