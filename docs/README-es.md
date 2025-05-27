**Español** | [English](/README.md) | [Português Brasileiro](/docs/README-pt_BR.md)

# Módulo Dataverse

Este plugin es el resultado de una colaboración entre SciELO Brasil y Lepidus. Permite integrar Open Journal Systems (OJS) y Open Preprint Systems (OPS) con un repositorio Dataverse.
De este modo, los autores pueden enviar los datos de investigación asociados a sus manuscritos durante el proceso de envío a la revista o al servidor de preprints. Los datos de investigación permanecen disponibles en el flujo editorial (por ejemplo, pueden estar disponibles durante la evaluación por pares del artículo o la moderación del preprint) y se vinculan a la publicación en OJS/OPS.

## Compatibilidad

Este plugin es compatible con las siguientes aplicaciones PKP:

- OPS y OJS en las versiones 3.3 y 3.4.

Consulte la última versión compatible con su aplicación en la [Página de Versiones](https://github.com/lepidus/dataversePlugin/releases).

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