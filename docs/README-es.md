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

1. **api_key_secret**

La instancia de OJS debe tener la configuración `api_key_secret` configurada, puedes contactar a tu administrador de sistema para hacerlo (ver [esta publicación](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

Esto es necesario para utilizar las credenciales de API proporcionadas, que se almacenan cifradas en la base de datos de OJS.

## Instalación

### Instrucciones

Este módulo está disponible para su instalación a través de la [Galería de Plugins PKP](https://docs.pkp.sfu.ca/plugin-inventory/en/). Para instalarlo, siga estos pasos:

1. Acceda a la zona __Panel de control__ de su sitio.
2. Navegue hasta `Ajustes` > `Sítio web` > `Módulos` > `Galería de módulos`.
3. Busque el módulo llamado `Módulo Dataverse` y haga clic en su nombre.
4. En la ventana que se abre, haga clic en `Instalar` y confirme que desea instalar el módulo.

Siguiendo estos pasos, el módulo se instalará en tu OJS/OPS. Después de la instalación, cuando quieras comprobar si hay una nueva versión disponible, sólo tienes que seguir la misma ruta y comprobar el estado del módulo en la lista.

## Reparación de una versión incorrecta del núcleo (OJS/OPS 3.4)

Las versiones anteriores del módulo podían registrar la versión de Dataverse como versión del
núcleo de la aplicación. La actualización elimina esos registros incorrectos y restaura la mayor
versión real de OJS/OPS 3.4.0 presente en la base de datos, conservando su fecha de instalación.
Los registros eliminados se escriben en el registro de errores de PHP.

Los administradores de sitios afectados deben ejecutar `php tools/upgrade.php upgrade` desde la
raíz de la aplicación después de actualizar el módulo. Esto es especialmente importante si OJS/OPS
se actualizó después de la corrupción: restaurar la versión registrada permite ejecutar los pasos
de actualización del núcleo que se omitieron. La reparación no marca esos pasos como completados
ni crea un registro de versión para los archivos actuales.

## Recuperación de depósitos y tiempos de espera

La tabla `dataverse_deposits` registra una operación de depósito por envío, con su estado, revisión,
manifiesto de archivos y recibos de las cargas confirmadas. Si falla la espera del ingest antes de
enviar una carga, la operación vuelve a `ready`; un nuevo intento con los mismos archivos reutiliza
el dataset y omite las cargas ya confirmadas. Cuando un dataset depositado ya no existe en Dataverse,
se elimina su asociación local y se retira la operación completada, permitiendo otro depósito o asociación.

Una respuesta perdida después de crear el dataset o cargar un archivo deja un resultado desconocido
(`creating` o `uploading`). Estas operaciones no se reinician ni se repiten automáticamente. Un
administrador debe reconciliar la operación con la colección Dataverse configurada: comprobar el
identificador persistente, el manifiesto, los archivos remotos y los recibos (un ZIP puede generar
varios archivos). Antes de corregir registros manualmente, haga una copia de seguridad y detenga los
depósitos activos; conserve los recibos confirmados e incremente la revisión al cambiar el estado.
No reinicie una operación solamente porque se agotó el tiempo de espera.

Estas opciones de `config.inc.php` muestran los límites totales predeterminados de las peticiones,
en segundos. Utilice enteros de 1 a 300. El límite de conexión es de 5 segundos; la espera separada
del ingest es de 30 segundos y no cambia con estas opciones.

```ini
[dataverse]
read_timeout = 15
create_timeout = 30
upload_timeout = 60
write_timeout = 30
download_timeout = 300
```

## Instrucciones de uso

### Configuración
Después de la instalación, necesitas habilitar el módulo. Esto se hace en `Ajustes`> `Sitio web`> `Módulos`> `Módulos instalados`.

Con el módulo habilitado, debe ampliar sus opciones haciendo clic en la flecha situada junto al nombre del módulo y, a continuación, haciendo clic en `Ajustes`.

En la nueva ventana aparecerán las opciones _Dataverse URL_, _Token de API_, _Términos de uso_ y _Instrucciones Adicionales_.

Debe introducir la URL completa del repositorio Dataverse donde se depositarán los datos de la búsqueda. Por ejemplo: `https://demo.dataverse.org/dataverse/anotherdemo`.

Las condiciones de uso pueden definirse para cada idioma configurado en su aplicación. Si tiene dudas sobre cuáles son los términos, consulte al responsable de su repositorio.

**Importante**: El `Token de API` pertenece a una cuenta de usuario de Dataverse. Para más información sobre cómo obtener el token de API, consulte la [Guía del usuario de Dataverse](https://guides.dataverse.org/en/5.13/user/account.html#api-token). Una vez guardado, el token ya no se muestra en la configuración: deje el campo en blanco para mantenerlo o introduzca un nuevo token para reemplazarlo. Cambiar el servidor de Dataverse requiere introducir el token de nuevo.

Es importante mencionar que la cuenta de usuario de Dataverse se incluirá en la lista de contribuyentes de los conjuntos de datos depositados a través del plugin (para más información, consulta [esta discusión](https://groups.google.com/g/dataverse-community/c/Oo4AUZJf4hE/m/DyVsQq9mAQAJ)).

Por lo tanto, se recomienda crear un usuario específico para la revista o servidor de preprints, en lugar de utilizar una cuenta personal, ya que cada depósito estará asociado con esta cuenta.

Después de completar los campos, solo confirma la acción haciendo clic en `Guardar`. El plugin funcionará solo después de completar esta configuración.

### Uso

Se añade una sección llamada "Datos de investigación" al paso "Archivos" durante el proceso de envío. Además, los metadatos del conjunto de datos deben completarse en el paso "Para los editores/as".

Los autores, moderadores, editores o gerentes también pueden editar el conjunto de datos antes de su publicación, en la pestaña "Datos de investigación" mostrada en el flujo de trabajo de envío.

En OJS, los evaluadores pueden recibir acceso a los archivos de datos de investigación durante el proceso de evaluación. El acceso de los evaluadores a estos archivos puede ser restringido en la Configuración del Flujo de Trabajo, para que solo visualicen los archivos cuando acepten evaluar la presentación.

## Instrucciones para desarrollo:
1. Clona el repositorio del plugin Dataverse.
2. Para utilizar el plugin en una aplicación PKP, copia su directorio en el directorio `/plugins/generic`, asegurándote de que el directorio se llame `dataverse`.
3. Desde la raíz del directorio de la aplicación PKP, ejecuta el siguiente comando para actualizar la base de datos, creando las tablas utilizadas por el plugin:
    - `php tools/upgrade.php upgrade`

## Ejecución de pruebas
### Pruebas unitarias

Para ejecutar las pruebas unitarias, ejecuta el siguiente comando en la raíz del directorio de tu aplicación PKP:

```
find plugins/generic/dataverse -name tests -type d -exec php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit-env2.xml -v "{}" ";"
```

### Pruebas de aceptación

Crea un archivo cypress.env.json en la raíz del directorio de tu aplicación PKP, con las siguientes variables:
- `baseUrl`
- `dataverseUrl`
- `dataverseApiToken`
- `dataverseTermsOfUse`

**Ejemplo:**

```json
{
    "baseUrl": "http://localhost:8000",
    "dataverseUrl": "https://demo.dataverse.org/dataverse/myDataverseAlias",
    "dataverseApiToken": "abcd-abcd-abcd-abcd-abcdefghijkl",
    "dataverseTermsOfUse": "https://dataverse.org/best-practices/harvard-dataverse-general-terms-use",
    "dataverseAdditionalInstructions": "Instruções adicionar sobre submissão de dados de pesquisa:"
}
```

Luego, para ejecutar las pruebas de Cypress, ejecuta el siguiente comando desde la raíz de la aplicación:
```
npx cypress run --config specPattern=plugins/generic/dataverse/cypress/tests
```

Para ejecutar las pruebas con la interfaz de usuario de Cypress, ejecuta:
```
npx cypress open --config specPattern=plugins/generic/dataverse/cypress/tests
```

Importante: Cypress busca elementos utilizando cadenas exactas. El idioma de tu aplicación PKP debe estar en inglés para pasar las pruebas.

## Créditos

Este plugin fue patrocinado por la Scientific Electronic Library Online (SciELO) y desarrollado por Lepidus Tecnologia.

El desarrollo de este plugin pretende dar continuidad a la integración entre OJS y Dataverse, realizada anteriormente a través de [módulo para OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## Licencia

__Este plugin está licenciado bajo la GNU General Public Licence v3.0__

__Copyright (c) 2021-2025 Lepidus Tecnologia__

__Copyright (c) 2021-2025 SciELO__