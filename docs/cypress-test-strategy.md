# Estratégia de testes Cypress

Esta matriz registra a camada mais barata que preserva a garantia de cada cenário atual. “Manter” significa que a segurança depende da integração entre navegador, componentes PKP, permissões ou hooks. “Migrar” só autoriza remover o Cypress depois que o teste substituto existir e uma execução isolada comprovar a equivalência.

| Spec | Cenário | Camada recomendada | Decisão e garantia preservada |
| --- | --- | --- | --- |
| Test0_0 | Submissão antes da configuração | Cypress sem Dataverse externo | Manter como smoke do hook e da renderização sem configuração; executável isoladamente na VM local. |
| Test0 | Configurar o plugin | Cypress com Dataverse externo | Manter um único cenário da interface de configuração e da validação real do token; outras preparações devem usar fixture. |
| Test1 | Campos e validações da declaração de dados | Cypress com backend controlado | Manter uma prova da exibição e do erro retornado; cobrir combinações exaustivas no dispatcher/formulário. |
| Test1 | Exibir campos somente ao escolher depósito | Cypress sem Dataverse externo | Manter porque valida reatividade e visibilidade no Vue. |
| Test1 | Arquivo de dados igual à composição | Unidade + um smoke Cypress | Migrar a comparação exaustiva para `DraftDatasetFilesValidatorTest`; manter só a apresentação do erro. |
| Test1 | README obrigatório e não exclusivo | Unidade + um smoke Cypress | Migrar nomes/MIME/combinações para o validator; manter uma prova de erro e correção na interface. |
| Test1 | Adicionar metadados do conjunto | Cypress com backend controlado | Manter uma prova de autosave e revisão; empacotamento já pertence aos testes PHP. |
| Test2 | Declaração de dados no workflow | Cypress com backend controlado | Manter porque cobre hidratação, edição e persistência do formulário no workflow. |
| Test2 | Editar metadados no workflow | Cypress com backend controlado | Manter uma prova de persistência após nova leitura; regras de serialização ficam em unidade. |
| Test2 | Adicionar e excluir arquivos no workflow | Cypress com backend controlado | Manter para wiring do modal/lista; validar regras e handler em PHP. |
| Test2 | Autor excluir conjunto | Integração de handler + smoke Cypress | Cobrir resposta, associação e erro no handler; manter uma ação pela interface. |
| Test2 | Autor depositar conjunto | Smoke Cypress com Dataverse real | Manter como uma das poucas provas ponta a ponta externas. |
| Test2 | Registrar ações no histórico | Integração PHP com banco | Migrar: a garantia é emissão/persistência dos eventos, não a tabela visual do core. |
| Test2 | Bloquear autor sem permissão | Cypress sem Dataverse externo | Manter porque o estado desabilitado resulta da integração de permissão e Vue. |
| Test2 | Editor excluir e notificar | Integração de handler/mail + smoke Cypress | Testar destinatário, mensagem e remoção sem navegador; manter uma abertura/confirmação do modal. |
| Test2 | Editor depositar conjunto | Integração de autorização | Migrar a duplicação do depósito do autor; testar o papel no handler e conservar um único depósito E2E. |
| Test2 | Publicar conjunto ao publicar submissão | Cypress com backend controlado | Manter porque cobre o modal acrescido pelo plugin ao fluxo editorial do core. |
| Test2 | Publicar conjunto após a submissão | Smoke Cypress com Dataverse real | Manter como prova externa de publicação e bloqueio posterior das ações. |
| Test2 | Nova versão não republicar conjunto | Integração de evento + Cypress reduzido | Cobrir a regra negativa em PHP; manter apenas a ausência da opção no modal. |
| Test3 | Criar submissão com dados para revisão | Cypress com servidor controlado, por enquanto | A rede externa foi removida; a criação visual ainda prepara o fluxo encadeado e só deve sair após uma fixture de submissão provar o mesmo estado. |
| Test3 | Editor selecionar arquivos para revisores | Cypress com backend controlado | Manter porque integra decisão editorial, formulário e arquivos do plugin. |
| Test3 | Revisor visualizar arquivos selecionados | Cypress com backend controlado | Manter por envolver autorização, papel, UI e links disponibilizados. |
| Test3 | Configurar publicação na decisão | HTTP controlado | Migrado para configuração direta no `before`; a persistência é provada pela presença das ações de excluir/publicar nas decisões seguintes. |
| Test3 | Recusa excluir conjunto | Cypress com servidor controlado | Manter uma decisão editorial completa; a exclusão externa real pertence ao smoke reduzido. |
| Test3 | Reverter recusa e reenviar dados | Fixture + integração de evento | Migrar: upload e depósito já possuem cobertura própria; testar somente a transição relevante. |
| Test3 | Aceite publicar conjunto | Cypress com servidor controlado | Manter porque prova decisão, hook, publicação HTTP e releitura da citação; a compatibilidade externa continua no smoke do Test2. |
| Test4 | Informações na página pública | Cypress sem escrita externa | Manter um teste de renderização pública e links; usar dataset controlado/fixture. |
| Test4 | Omitir declaração quando só houve depósito | Integração de template + um Cypress | Cobrir combinações condicionais em PHP; manter uma prova pública negativa. |
| Test5 | Desabilitar plugin | Preparação por API/CLI | Migrar; não é necessário retestar a interface genérica de plugins do PKP. |
| Test5 | Iniciar submissão com plugin desabilitado | Cypress sem Dataverse externo | Manter como regressão de compatibilidade de submissão incompleta. |
| Test5 | Reabilitar plugin | Preparação por API/CLI | Migrar; é preparação para a regressão, não comportamento próprio. |
| Test5 | Concluir submissão iniciada sem o plugin | Cypress sem Dataverse externo | Manter e unir ao cenário anterior para provar o ciclo desabilitado/reativado. |
| Test6 | Criar submissão para associação | Fixture de estado | Migrar a preparação; não é a ação sob teste neste spec. |
| Test6 | Desassociar conjunto | Cypress com backend controlado | Manter uma prova do modal, resposta e mudança persistida. |
| Test6 | Rejeitar identificador persistente inválido | Integração do handler | Migrar validação, status e mensagem; um erro visual genérico já é coberto em outra prova. |
| Test6 | Associar novamente por identificador | Smoke Cypress com Dataverse real | Manter como prova externa de busca, associação e apresentação do conjunto. |
| Test7 | Placeholder quando URL não está configurada | Remover | Não testa comportamento do plugin; a seleção da suíte deve ocorrer no manifesto/CI. |
| Test7 | Configurar metadados obrigatórios customizados | API/fixture de configuração | Migrar a preparação; a tela de configuração já tem um cenário dedicado. |
| Test7 | Exibir campos customizados | Cypress com servidor controlado | Manter porque valida schema dinâmico, tipos de campo e renderização. |
| Test7 | Impedir envio com campos vazios | Integração de formulário + um Cypress | Migrar a matriz de obrigatoriedade; manter uma apresentação de erro na revisão. |
| Test7 | Impedir envio com valores inválidos | Unidade + integração do dispatcher | Migrado para `RequiredMetadataFieldValidatorTest` e `DatasetMetadataDispatcherTest`: URL, data e e-mail válidos/inválidos, formato ISO, tipos desconhecidos e wiring do hook; o Cypress conserva obrigatoriedade, renderização e envio válido. |
| Test7 | Enviar com valores válidos | Cypress com servidor controlado | Manter uma prova do fluxo de correção e submissão bem-sucedida. |
| Test7 | Depositar metadados customizados no workflow | Integração de packager + smoke externo reduzido | O packager deve provar todos os campos; conservar um campo representativo no E2E externo. |

## Sequência de migração

1. Adicionar cobertura PHP para validator de arquivos, eventos, histórico, autorização e validação dos campos customizados.
2. Criar fixtures idempotentes para submissão, estágio editorial, associação e configuração.
3. Trocar preparações visuais pelas fixtures sem remover as asserções de interface relevantes.
4. Executar cada spec isoladamente e em ordem aleatória.
5. Somente então remover cenários redundantes, separar o smoke externo e habilitar paralelização.

Até essas condições serem atendidas, os Cypress marcados para migração permanecem ativos. Isso evita uma redução temporária de cobertura durante a reorganização.
