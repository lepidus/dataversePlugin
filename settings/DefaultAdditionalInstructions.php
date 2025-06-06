<?php

namespace APP\plugins\generic\dataverse\settings;

class DefaultAdditionalInstructions
{
    public function getDefaultInstructions(): array
    {
        return [
            'en' => $this->getEnglishInstructions(),
            'es' => $this->getSpanishInstructions(),
            'pt_BR' => $this->getPortugueseInstructions(),
        ];
    }

    private function getEnglishInstructions()
    {
        return '<p>1. Submit under "Research Data" any files that have been collected, observed,
                generated, or used in the scientific research and that facilitate the evaluation,
                validation, understanding, and reproduction of the research (e.g.: Databases, submitted
                questionnaires, anonymized responses, etc).</p>
            <p>2. It is mandatory to include a file named "Readme" (in .txt or .pdf format) containing
                information about the dataset and data files. This file should describe the research
                context, methodology, file structure, variables, and usage instructions, following
                best practices for scientific data management to ensure accessibility, understanding,
                and proper reuse of the data both for the researcher and for others who may want to
                use them in the future. Without this documentation, data may be lost, become inaccessible
                or difficult to interpret, compromising their scientific utility and preservation of research data.</p>
            <p>For additional guidance on creating the file, consult the suggested references
                below:</p>
            <ul>
                <li><a href="https://drive.google.com/drive/folders/1LNxx4YKxN1a2DF10bbVoRW-ioZwNz-9M">SciELO Data Templates</a></li>
                <li><a href="https://data.research.cornell.edu/data-management/sharing/readme/">Guide for writing "Readme" style metadata</a></li>
                <li><a href="https://drive.google.com/file/d/167cJdaRy4sxQWA5qEEp2cgfWqKV-smZV/view?pli=1">DataverseNO Readme Template</a></li>
                <li><a href="https://social-science-data-editors.github.io/template_README/template-README.html">Readme Template for Social Sciences</a></li>
            </ul>
            <p>3. The files deposited in "Research Data" will form a dataset created in the Dataverse
                installation related to the journal. If the manuscript is approved, the dataset will be
                published in open access.</p>';
    }

    private function getSpanishInstructions()
    {
        return '<p>1. Envíe en "Datos de investigación" archivos que hayan sido recopilados,
                observados, generados o utilizados por la investigación científica y que faciliten la evaluación,
                validación, comprensión y reproducción de la investigación (ej.: Bases de datos, cuestionarios
                enviados, respuestas anonimizadas, etc).</p>
            <p>2. Es obligatorio incluir un archivo llamado "Readme" (en formato .txt o .pdf) que contenga
                información del conjunto y archivos de datos. Este archivo debe describir el contexto de la
                investigación, metodología, estructura de los archivos, variables e instrucciones de uso, siguiendo
                las mejores prácticas de gestión de datos científicos para asegurar la accesibilidad,
                comprensión y reutilización adecuada de los datos tanto para el propio investigador
                como para otras personas que quieran utilizarlos en el futuro. Sin esta documentación,
                los datos pueden perderse, volverse inaccesibles o difíciles de interpretar, comprometiendo
                su utilidad científica y la preservación de los datos de investigación.</p>
            <p>Para orientaciones adicionales sobre la creación del archivo, consulte las referencias sugeridas
                a continuación:</p>
            <ul>
                <li><a href="https://drive.google.com/drive/folders/1LNxx4YKxN1a2DF10bbVoRW-ioZwNz-9M">Modelos de SciELO Data</a></li>
                <li><a href="https://data.research.cornell.edu/data-management/sharing/readme/">Guía para escribir metadatos en estilo "Readme"</a></li>
                <li><a href="https://drive.google.com/file/d/167cJdaRy4sxQWA5qEEp2cgfWqKV-smZV/view?pli=1">Modelo de Readme de DataverseNO</a></li>
                <li><a href="https://social-science-data-editors.github.io/template_README/template-README.html">Modelo de Readme para Ciencias Sociales</a></li>
            </ul>
            <p>3. Los archivos depositados en "Datos de Investigación" compondrán un conjunto de datos creado en
                la instalación Dataverse relacionada con la revista. Si el manuscrito es aprobado, el conjunto será
                publicado en acceso abierto.</p>';
    }

    private function getPortugueseInstructions()
    {
        return '<p>1. Submeta em "Dados de pesquisa" arquivos que tenham sido coletados,
                observados, gerados ou usados pela pesquisa científica e que facilitem a avaliação,
                validação, compreensão e reprodução da pesquisa (ex.: Banco de dados, questionários
                enviados, respostas anonimizadas, etc).</p>
            <p>2. É obrigatória a inclusão de um arquivo nomeado "Readme" (em formato .txt ou .pdf) contendo
                informações do conjunto e arquivos de dados. Este arquivo deve descrever o contexto da
                pesquisa, metodologia, estrutura dos arquivos, variáveis e instruções de uso, seguindo
                as melhores práticas de gestão de dados científicos para assegurar acessibilidade,
                compreensão e reutilização adequada dos dados tanto para a própria pessoa pesquisadora
                quanto para outras pessoas que queiram utilizá-los no futuro. Sem essa documentação,
                dados podem se perder, tornar-se inacessíveis ou difíceis de interpretar, comprometendo
                a sua utilidade científica e preservação dos dados de pesquisa.</p>
            <p>Para orientações adicionais quanto a criação do arquivo, consulte as referências sugeridas
                abaixo:</p>
            <ul>
                <li><a href="https://drive.google.com/drive/folders/1LNxx4YKxN1a2DF10bbVoRW-ioZwNz-9M">Modelos da SciELO Data</a></li>
                <li><a href="https://data.research.cornell.edu/data-management/sharing/readme/">Guia para escrever metadados no estilo "Readme"</a></li>
                <li><a href="https://drive.google.com/file/d/167cJdaRy4sxQWA5qEEp2cgfWqKV-smZV/view?pli=1">Modelo de Readme da DataverseNO</a></li>
                <li><a href="https://social-science-data-editors.github.io/template_README/template-README.html">Modelo de Readme para Ciências Sociais</a></li>
            </ul>
            <p>3. Os arquivos depositados em "Dados de Pesquisa", comporão um conjunto de dados criado na
                instalação Dataverse relacionada ao periódico. Se o manuscrito for aprovado, o conjunto será
                publicado em acesso aberto.</p>';
    }
}
