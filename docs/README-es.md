**Español** | [English](/README.md) | [Português Brasileiro](/docs/README-pt_BR.md)

# Módulo Dataverse

Estamos implementando este módulo para OPS y OJS 3.3 (o superior) para SciELO Brasil.

Es un trabajo en progreso, la versión actual es un MVP para OPS y OJS.

## Compatibilidad

La última versión de este plugin es compatible con las siguientes aplicaciones PKP:

* OPS 3.4.0
* OJS 3.4.0

Usando PHP 8.1 o posterior.

Compatible con Dataverse 5.x y 6.x.

## Descargar el plugin 

Para descargar el plugin, vaya a la [Página de Versiones](https://github.com/lepidus/dataversePlugin/releases) y descargue el paquete tar.gz de la última versión compatible con su sitio web.

## Instalación

### Instrucciones

1. Instale las dependencias del módulo
2. Entre en el área administrativa de su sitio a través del __Panel de Control__.
3. Navegue hasta `Ajustes`> `Sitio Web`> `Módulos`> `Cargar un nuevo módulo`.
4. En __Subir fichero__, selecciona el archivo __dataverse.tar.gz__.
5. Haga clic en __Guardar__ y el módulo se instalará en su sitio.

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

# Licencia

__Este plugin está licenciado bajo la GNU General Public Licence v3.0__

__Copyright (c) 2021-2024 Lepidus Tecnologia__

__Copyright (c) 2021-2024 SciELO__