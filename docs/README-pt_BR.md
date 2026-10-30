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

1. **app_key**

A instância do OJS/OPS deve ter a chave da aplicação (`app_key`, na seção `[general]` do `config.inc.php`) configurada. A instalação e a atualização para a 3.5 a criam; se estiver ausente, o administrador do sistema pode gerá-la com `php lib/pkp/tools/appKey.php generate`.

Isso é necessário para utilizar as credenciais de API fornecidas, que são armazenadas criptografadas no banco de dados. Trocar a chave depois torna as credenciais armazenadas ilegíveis, e elas precisam ser informadas novamente nas configurações do plugin.

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

#### Limites de tempo das requisições
As conexões com o Dataverse têm limite de 5 segundos, e cada requisição tem um limite total que depende da operação: 15 segundos para leituras, 60 segundos para o download de cada arquivo, 30 segundos para a criação do dataset, 60 segundos para o envio de cada arquivo e 30 segundos para as demais alterações (inclusive exclusões). Administradores podem alterar esses limites no `config.inc.php` da aplicação:

```ini
[dataverse]
read_timeout = 15
download_timeout = 60
create_timeout = 30
upload_timeout = 60
write_timeout = 30
```

Os valores devem ser números inteiros de segundos entre 1 e 300, escritos sem zero à esquerda (a aplicação lê `060` como número octal); qualquer outro valor, como `on` ou `1.5`, usa o padrão. Os arquivos são depositados um após o outro na mesma requisição, então, antes de aumentar um limite, verifique se os limites de requisição do PHP e do servidor web cobrem o tempo de todos os envios juntos. Uma requisição que atinge o limite é informada como tempo esgotado: o Dataverse pode ter concluído a operação mesmo assim.

### Uso

Uma seção chamada "Dados de pesquisa" é adicionada ao passo "Arquivos" durante o processo de submissão. Além disso, os metadados do conjunto de dados devem ser preenchidos no passo "Para editores".

Quando o plugin cria um conjunto de dados, ele preenche a Data de depósito (Deposit Date) dos metadados de citação com o dia do depósito; editar os metadados depois mantém essa data. Ela nunca é pedida aos autores, mesmo que a coleção a marque como obrigatória.

Autores, moderadores, editores ou gerentes também podem editar o conjunto de dados, antes de sua publicação, no item "Dados de pesquisa" adicionado à seção Dataverse do menu do fluxo de trabalho da submissão. Ao lado dele é adicionado um item "Declaração de dados", para que a declaração de disponibilidade de dados possa ser consultada e alterada durante o fluxo de trabalho editorial.

O "Relatório do Dataverse", em Estatísticas > Gerador de Relatórios, gera um CSV para um período de datas de submissão ou de datas de decisão final. A decisão final é uma aceitação ou rejeição que nenhuma decisão posterior desfez, como devolver a submissão à avaliação ou reverter a rejeição (enviá-la à produção mantém a aceitação); no OPS, é a primeira publicação do preprint ou a sua rejeição; e uma submissão aceita e rejeitada depois é contada apenas como rejeitada. O CSV traz as submissões aceitas (OJS) ou publicadas (OPS), rejeitadas e no total, quantas delas têm dados de pesquisa, os tipos de declaração de dados de cada grupo, os conjuntos de dados com erros de depósito ou de publicação e o número de arquivos nos conjuntos de dados depositados.

A citação do conjunto de dados e seus indicadores de status aparecem no cabeçalho de Dados de pesquisa, com o mesmo estilo de status usado em Publicação. Os arquivos são exibidos acima do formulário de metadados, sem abas.

Antes de enviar cada arquivo, o plugin espera até 30 segundos para que o Dataverse termine de processar (ingest) os arquivos que já estão no conjunto de dados. Se o Dataverse ainda estiver processando depois desse tempo, o arquivo não é enviado e um erro pede para tentar novamente em alguns minutos; quando isso acontece durante um depósito, o conjunto de dados e os arquivos já enviados são mantidos, e um novo depósito continua a partir do arquivo que não foi enviado.

No OJS, avaliadores podem receber acesso aos arquivos de dados de pesquisa durante o processo de avaliação. O acesso dos avaliadores à esses arquivos pode ser restringido nas Configurações do Fluxo de Trabalho, para que eles visualizem os arquivos apenas quando aceitarem avaliar a submissão.

#### Depósitos interrompidos
O plugin registra cada etapa do depósito antes de chamar o Dataverse. Se um depósito for interrompido depois que o conjunto de dados foi criado, uma nova tentativa reutiliza o mesmo conjunto de dados: envia os metadados de novo, para que alterações feitas nesse meio-tempo sejam mantidas, e envia apenas os arquivos que ainda não foram confirmados. Se os arquivos de dados de pesquisa foram alterados nesse meio-tempo, o conjunto de dados parcial é excluído e o depósito recomeça com a nova seleção. Quando o Dataverse recusa uma etapa (uma licença inválida ou um token expirado, por exemplo), nada foi alterado lá, o erro é exibido e o depósito pode ser tentado novamente depois de corrigida a causa. Quando o resultado de uma etapa é desconhecido — a requisição atingiu o limite de tempo ou o processo parou durante a criação do conjunto de dados ou o envio de um arquivo —, novas tentativas para aquela submissão são recusadas com uma mensagem pedindo para entrar em contato com a equipe editorial, para que o plugin nunca crie um conjunto de dados duplicado nem envie um arquivo duas vezes.

Esses depósitos devem ser conciliados por um administrador, diretamente no banco de dados, apenas para a submissão afetada:

1. Garanta que nenhuma requisição da submissão ainda esteja em execução e faça backup das suas linhas em `dataverse_deposits`, `dataverse_studies` e `draft_dataset_files`.
2. No estado `creating`, verifique no Dataverse se o conjunto de dados foi criado. Se foi, salve o seu identificador persistente em `persistent_id` e siga para o passo 4. Se o Dataverse comprovar que nada foi criado, mude o estado para `retired` e incremente `revision`, restrito ao `submission_id`, ao `state` e à `revision` inspecionados: o próximo depósito começa do início.
3. No estado `uploading`, verifique quais arquivos do manifesto chegaram ao conjunto de dados e registre os seus ids do Dataverse em `receipts`; um arquivo que não chegou ao Dataverse fica sem recibo.
4. Com `persistent_id` preenchido, em uma única transação restrita ao `submission_id`, ao `state` e à `revision` inspecionados, mude o estado para `ready` e incremente `revision`; depois, tente o depósito novamente pelo painel "Dados de pesquisa".
5. Se a URL do Dataverse mudou depois que o conjunto de dados foi criado, o depósito não pode continuar na nova coleção: mantenha ou exclua o conjunto de dados na anterior conforme o caso e, depois, mude o estado para `retired` e incremente `revision`.

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
export DATAVERSE_CUSTOM_REQUIRED_METADATA_URL="https://demo.dataverse.org/dataverse/customRequiredMetadataFields"
```

`DATAVERSE_CUSTOM_REQUIRED_METADATA_URL` aponta para uma coleção, no mesmo servidor e acessível com o mesmo token, cujos blocos de metadados exigem `alternativeURL`, `dsDescriptionDate`, `PSRI1` e `PSRI2`; ela é usada pelos cenários de metadados exigidos pela coleção. Sem essas variáveis, os testes PHPUnit que precisam delas são ignorados localmente e falham na CI, e a suíte Playwright não inicia.

### Testes de Unidade e de Integração (PHPUnit)

Execute na raíz do diretório da sua aplicação PKP:
```
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit.xml plugins/generic/dataverse/tests
```

Os cenários de configuração, de submissão, do fluxo de trabalho e da página pública, incluindo os metadados exigidos por uma coleção, rodam contra o banco de dados da aplicação, arquivos reais e a coleção Dataverse acima, sem mocks.

### Testes de Ponta a Ponta (Playwright)

Aqui é testado apenas o comportamento que só existe no navegador: a validação do formulário de configuração feita no navegador, os campos e seções condicionais do assistente de submissão, o modal de envio de dados de pesquisa, a etapa de revisão, os painéis Dados de pesquisa e Declaração de dados do fluxo de trabalho, as etapas das decisões editoriais, os dados de pesquisa exibidos aos avaliadores e a página pública. Os cenários de publicação e de aceite publicam um dataset na coleção de teste a cada execução, e o Dataverse não permite que um token de API comum apague um dataset publicado, então use uma coleção destinada a testes.

A aplicação precisa estar em execução com o conjunto de dados de teste de referência (contexto `publicknowledge`, usuários `dbarnes`, `eostrom` e, no OJS, `jjanssen`) e o idioma `en`. No diretório do plugin:
```
npm install
npx playwright install chromium
npm run test:e2e
```

`BASE_URL` aponta para a aplicação (padrão `http://localhost:8000`) e `APP_ROOT` para o diretório raíz dela (padrão: três níveis acima do plugin). Para rodar no OPS, defina as duas, por exemplo `APP_ROOT=/caminho/para/ops BASE_URL=http://localhost:8001 npm run test:e2e`.

## Créditos

Este plugin foi patrocinado pela Scientific Electronic Library Online (SciELO) e desenvolvido por Lepidus Tecnologia.

O desenvolvimento deste plugin busca dar continuidade a integração entre OJS e Dataverse, feita anteriormente através do [plugin para OJS 2.4](https://github.com/asmecher/dataverse-ojs-plugin).

## Licença

__Este plugin é licenciado sob a GNU General Public License v3.0__

__Copyright (c) 2021-2025 Lepidus Tecnologia__

__Copyright (c) 2021-2025 SciELO__