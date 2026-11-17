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

## Reparo de uma versão incorreta do core (OJS/OPS 3.4)

Versões anteriores do plugin podiam registrar a versão do Dataverse como versão do core da aplicação.
A atualização do plugin remove esses registros incorretos e restaura a maior versão real do OJS/OPS
3.4.0 presente no banco, preservando sua data de instalação. Os registros removidos são gravados no
log de erros do PHP.

Administradores de instalações afetadas devem executar `php tools/upgrade.php upgrade` na raiz da
aplicação após atualizar o plugin. Isso é especialmente importante se o OJS/OPS foi atualizado depois
da corrupção: restaurar a versão registrada permite executar as etapas do upgrade do core que foram
ignoradas. O reparo não marca essas etapas como concluídas nem cria um registro de versão para os
arquivos atuais.

## Recuperação de depósitos e tempos limite

A tabela `dataverse_deposits` acompanha uma operação de depósito por submissão, incluindo estado,
revisão, manifesto dos arquivos de origem e recibos dos uploads confirmados. Se a espera pelo ingest
falhar antes do envio, a operação volta a `ready`; uma nova tentativa com os mesmos arquivos reutiliza
o dataset e ignora os uploads já confirmados. Quando um dataset depositado não existe mais no
Dataverse, a associação local é removida e a operação concluída é aposentada, permitindo novo
depósito ou associação.

Uma resposta perdida após a criação ou o upload deixa o resultado desconhecido (`creating` ou
`uploading`). Essas operações não são reiniciadas nem repetidas automaticamente. Um administrador
deve reconciliar a operação com a coleção Dataverse configurada: conferir identificador persistente,
manifesto, arquivos remotos e recibos (um ZIP pode gerar vários arquivos remotos). Antes de corrigir
registros manualmente, faça backup do banco e interrompa os depósitos ativos; preserve os recibos
confirmados e incremente a revisão ao mudar o estado. Não reinicie uma operação apenas por timeout.

As configurações opcionais abaixo, em `config.inc.php`, mostram os limites totais padrão das
requisições, em segundos. Use inteiros de 1 a 300. O limite de conexão é de 5 segundos; a espera
separada pelo ingest é de 30 segundos e não é alterada por essas configurações.

```ini
[dataverse]
read_timeout = 15
create_timeout = 30
upload_timeout = 60
write_timeout = 30
download_timeout = 300
```

## Instruções para uso

### Configuração
Após a instalação, é necessário habilitar o plugin. Isso é feito em `Configurações`> `Website`> `Plugins`> `Plugins instalados`.

Com o plugin habilitado, você deve expandir suas opções clicando na seta ao lado do nome do plugin e então clicando em `Configurações`.

Na nova janela, as configurações _URL Dataverse_, _Token de AIP_, _Termos de Uso_ e _Instruções Adicionais_ serão exibidas.

Você deve informar a URL completa para o repositório Dataverse onde os dados de pesquisa serão depositados. Por exemplo: `https://demo.dataverse.org/dataverse/anotherdemo`.

Os termos de uso pode ser definidos para cada idioma configurado em sua aplicação. Se você tiver dúvidas sobre quais são os termos, consulte o responsável pelo seu repositório.

**Importante:** O `Token de API` pertence à uma conta de usuário Dataverse. Para mais informações sobre como obter o token de API, veja o [Guia de Usuário do Dataverse](https://guides.dataverse.org/en/5.13/user/account.html#api-token). Depois de salvo, o token não é mais exibido nas configurações: deixe o campo em branco para mantê-lo ou informe um novo token para substituí-lo. Alterar o servidor do Dataverse exige informar o token novamente.

É importante mencionar que a conta de usuário do Dataverse será incluída na lista de contribuidores dos conjuntos de dados depositados via o plugin (para mais mais informações, veja [essa discussão](https://groups.google.com/g/dataverse-community/c/Oo4AUZJf4hE/m/DyVsQq9mAQAJ)).

Portanto, recomenda-se a criação de um usuário específico para o periódico ou servidor de preprints, ao invés de utilizar uma conta pessoal, visto que cada depósito será associado com essa conta.

Após preencher os campos, apenas confirme a ação clicando em `Salvar`. O plugin irá funcionar apenas após concluir essa configuração.

### Uso

Uma seção chamada "Dados de pesquisa" é adicionada ao passo "Arquivos" durante o processo de submissão. Além disso, os metadados do conjunto de dados devem ser preenchidos no passo "Para editores".

Autores, moderadores, editores ou gerentes também podem editar o conjunto de dados, antes de sua publicação, na aba "Dados de pesquisa" exibida no fluxo de trabalho da submissão.

No OJS, avaliadores podem receber acesso aos arquivos de dados de pesquisa durante o processo de avaliação. O acesso dos avaliadores à esses arquivos pode ser restringido nas Configurações do Fluxo de Trabalho, para que eles visualizem os arquivos apenas quando aceitarem avaliar a submissão.

## Instruções para Desenvolvimento:

1. Clone o repositório do plugin Dataverse
2. Para utilizar o plugin em uma aplicação PKP, copie o seu diretório para o diretório `/plugins/generic`, garantindo que o diretório chame-se `dataverse`.
3. Da raíz do diretório da aplicação PKP, execute o comando a seguir para atualizar o banco de dados, criando as tabelas utilizadas pelo plugin:
    * `php tools/upgrade.php upgrade`

## Executando testes

### Testes de Unidade

Para executar os testes de unidade, execute o seguinte comando na raíz do diretório de sua aplicação PKP:

```
find plugins/generic/dataverse -name tests -type d -exec php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit-env2.xml -v "{}" ";"
```

### Testes de Aceitação

Crie um arquivo `cypress.env.json` na raíz do diretório da sua aplicação PKP, com as seguintes variáveis:
- `baseUrl`
- `dataverseUrl`
- `dataverseApiToken`
- `dataverseTermsOfUse`

**Exemplo**:

```json
{
    "baseUrl": "http://localhost:8000",
    "dataverseUrl": "https://demo.dataverse.org/dataverse/myDataverseAlias",
    "dataverseApiToken": "abcd-abcd-abcd-abcd-abcdefghijkl",
    "dataverseTermsOfUse": "https://dataverse.org/best-practices/harvard-dataverse-general-terms-use",
    "dataverseAdditionalInstructions": "Instruções adicionar sobre submissão de dados de pesquisa:"
}
```

Em seguida, para executar o testes Cypress, execute o seguinte comando da raíz da aplicação:
```
npx cypress run --config specPattern=plugins/generic/dataverse/cypress/tests
```

Para executar os testes com interface de usuário Cypress, execute:
```
npx cypress open --config specPattern=plugins/generic/dataverse/cypress/tests
```

Importante: Cypress busca por elementos utilizando strings exatas. O idioma da sua aplicação PKP deve estar em inglês para passar nos testes.

## Créditos

Este plugin foi patrocinado pela Scientific Electronic Library Online (SciELO) e desenvolvido por Lepidus Tecnologia.

O desenvolvimento deste plugin busca dar continuidade a integração entre OJS e Dataverse, feita anteriormente através do [plugin para OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## Licença

__Este plugin é licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2021-2025 Lepidus Tecnologia__

__Copyright (c) 2021-2025 SciELO__