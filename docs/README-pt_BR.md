**Português Brasileiro** | [English](/README.md) | [Español](/docs/README-es.md)

# Plugin Dataverse

Este plugin é fruto da parceria entre a SciELO Brasil e a Lepidus. Ele permite integrar o Open Journal Systems (OJS) e o Open Preprint Systems (OPS) a um repositório Dataverse.

Assim, autores podem enviar os dados de pesquisa associados aos seus manuscritos durante o processo de submissão na revista ou no servidor de preprints. Os dados de pesquisa ficam disponíveis no fluxo editorial (por exemplo, podem ser disponibilizados na avaliação do artigo ou na moderação do preprint) e são associados à publicação no OJS/OPS.

Para um melhor entendimento da instalação e uso do plugin, veja nosso vídeo institucional: [Plugin Dataverse - Introdução e tutorial](https://app.heygen.com/videos/dataverse-plugin-introduction-and-tutorial-3cede08c38bf4065a4f769c1e35ae71d-en?source_video=true).

Esta é uma implementação inspirada no [plugin Dataverse original para o OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## Compatibilidade

Este plugin é compatível com as seguintes aplicações PKP:

- OPS e OJS nas versões 3.3 e 3.4

Verifique a última versão compatível com a sua aplicação na [Página de Versões](https://github.com/lepidus/dataversePlugin/releases).

Todas as versões são compatíveis com Dataverse 5.x e 6.x.

## Requisitos para uso

1. **api_key_secret**

A instância do OJS deve ter a configuração `api_key_secret` configurada, você pode contatar o administrador do sistema para fazer isso (consulte [este post](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

Isso é necessário para utilizar as credenciais de API fornecidas, que são armazenadas criptografadas no banco de dados do OJS.

## Instalação

Este plugin está disponível para instalação através da [Galeria de Plugins da PKP](https://docs.pkp.sfu.ca/plugin-inventory/en/). Para fazer a instalação, siga os seguintes passos:

1. Acesse a área do __Painel de Controle__ do seu site.
2. Navegue até `Configurações` > `Website` > `Plugins` > `Galeria de plugins`.
3. Procure pelo plugin chamado `Plugin Dataverse` e clique em seu nome.
4. Na janela que abrir, clique em `Instalar` e confirme que deseja instalar o plugin.

Seguindo esses passos, o plugin estará instalado em seu OJS/OPS. Após a instalação, quando desejar verificar se há uma nova versão disponível, basta seguir o mesmo caminho e verificar a situação do plugin na listagem.

## Instruções para uso

### Configuração
Após a instalação, é necessário habilitar o plugin. Isso é feito em `Configurações`> `Website`> `Plugins`> `Plugins instalados`.

Com o plugin habilitado, você deve expandir suas opções clicando na seta ao lado do nome do plugin e então clicando em `Configurações`.

Na nova janela, as configurações _URL Dataverse_, _Token de AIP_, _Termos de Uso_ e _Instruções Adicionais_ serão exibidas.

Você deve informar a URL completa para o repositório Dataverse onde os dados de pesquisa serão depositados. Por exemplo: `https://demo.dataverse.org/dataverse/anotherdemo`.

Os termos de uso pode ser definidos para cada idioma configurado em sua aplicação. Se você tiver dúvidas sobre quais são os termos, consulte o responsável pelo seu repositório.

**Importante:** O `Token de API` pertence à uma conta de usuário Dataverse. Para mais informações sobre como obter o token de API, veja o [Guia de Usuário do Dataverse](https://guides.dataverse.org/en/5.13/user/account.html#api-token).

É importante mencionar que a conta de usuário do Dataverse será incluída na lista de contribuidores dos conjuntos de dados depositados via o plugin (para mais mais informações, veja [essa discussão](https://groups.google.com/g/dataverse-community/c/Oo4AUZJf4hE/m/DyVsQq9mAQAJ)).

Portanto, recomenda-se a criação de um usuário específico para o periódico ou servidor de preprints, ao invés de utilizar uma conta pessoal, visto que cada depósito será associado com essa conta.

Após preencher os campos, apenas confirme a ação clicando em `Salvar`. O plugin irá funcionar apenas após concluir essa configuração.

### Uso

Uma seção chamada "Dados de pesquisa" é adicionada ao passo "Arquivos" durante o processo de submissão. Além disso, os metadados do conjunto de dados devem ser preenchidos no passo "Para editores".

Autores, moderadores, editores ou gerentes também podem editar o conjunto de dados, antes de sua publicação, no item "Dados de pesquisa" adicionado à seção Publicação do menu do fluxo de trabalho da submissão. Ao lado dele é adicionado um item "Declaração de dados", para que a declaração de disponibilidade de dados possa ser consultada e alterada durante o fluxo de trabalho editorial.

No OJS, avaliadores podem receber acesso aos arquivos de dados de pesquisa durante o processo de avaliação. O acesso dos avaliadores à esses arquivos pode ser restringido nas Configurações do Fluxo de Trabalho, para que eles visualizem os arquivos apenas quando aceitarem avaliar a submissão.

## Instruções para Desenvolvimento:

1. Clone o repositório do plugin Dataverse
2. Para utilizar o plugin em uma aplicação PKP, copie o seu diretório para o diretório `/plugins/generic`, garantindo que o diretório chame-se `dataverse`.
3. Da raíz do diretório da aplicação PKP, execute o comando a seguir para atualizar o banco de dados, criando as tabelas utilizadas pelo plugin:
    * `php tools/upgrade.php upgrade`

## Executando testes

Os testes que se comunicam com o Dataverse usam uma coleção Dataverse real. Exporte as credenciais dela antes de executá-los:

```
export DATAVERSE_URL="https://demo.dataverse.org/dataverse/myDataverseAlias"
export DATAVERSE_API_TOKEN="abcd-abcd-abcd-abcd-abcdefghijkl"
export DATAVERSE_TERMS_OF_USE="https://dataverse.org/best-practices/harvard-dataverse-general-terms-use"
```

Sem elas, esses testes são ignorados localmente e falham na CI.

### Testes de Unidade e de Integração (PHPUnit)

Execute na raíz do diretório da sua aplicação PKP:
```
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit.xml plugins/generic/dataverse/tests
```

Os cenários de configuração e do fluxo de submissão rodam contra o banco de dados da aplicação, arquivos reais e a coleção Dataverse acima, sem mocks.

### Testes de Ponta a Ponta (Playwright)

Aqui é testado apenas o comportamento que só existe no navegador: a validação do formulário de configuração feita no navegador, os campos e seções condicionais do assistente de submissão, o modal de envio de dados de pesquisa e a etapa de revisão.

A aplicação precisa estar em execução com o conjunto de dados de teste de referência (contexto `publicknowledge`, usuários `dbarnes` e `eostrom`) e o idioma `en`. No diretório do plugin:
```
npm install
npx playwright install chromium
npm run test:e2e
```

`BASE_URL` aponta para a aplicação (padrão `http://localhost:8000`) e `APP_ROOT` para o diretório raíz dela (padrão: três níveis acima do plugin). Para rodar no OPS, defina as duas, por exemplo `APP_ROOT=/caminho/para/ops BASE_URL=http://localhost:8001 npm run test:e2e`.

### Testes Cypress

Os cenários de fluxo editorial, avaliação, site público, submissões legadas, vinculação de datasets e metadados obrigatórios personalizados ainda são testes Cypress. Crie um arquivo `cypress.env.json` na raíz do diretório da sua aplicação PKP com `baseUrl`, `dataverseUrl`, `dataverseApiToken` e `dataverseTermsOfUse` e execute:
```
npx cypress run --config specPattern=plugins/generic/dataverse/cypress/tests
```

O idioma do seu sistema operacional e da aplicação PKP deve ser `en` para que os testes passem.

## Créditos

Este plugin foi patrocinado pela Scientific Electronic Library Online (SciELO) e desenvolvido por Lepidus Tecnologia.

O desenvolvimento deste plugin busca dar continuidade a integração entre OJS e Dataverse, feita anteriormente através do [plugin para OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## Licença

__Este plugin é licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2021-2025 Lepidus Tecnologia__

__Copyright (c) 2021-2025 SciELO__