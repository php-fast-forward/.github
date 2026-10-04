<p align="center">
  <a href="README.md" lang="en">English</a> · <strong>Português (Brasil)</strong> · <a href="README.es.md" lang="es">Español</a>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="../assets/brand/fast-forward-logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="../assets/brand/fast-forward-logo.svg">
    <img src="../assets/brand/fast-forward-logo.svg" alt="Logo do PHP Fast Forward" width="640">
  </picture>
</p>

<p align="center">
  <strong>O framework PHP para quem quer desenvolver com agilidade sem abrir mão da arquitetura.</strong>
</p>

<p align="center">
  PSR-first • componível • orientado a eventos • pessoas + agentes
</p>

<p align="center">
  <a href="https://github.com/php-fast-forward/framework"><img src="https://img.shields.io/badge/Framework-core-1E293B?logo=github&logoColor=white" alt="Repositório do Framework"></a>
  <a href="https://github.com/php-fast-forward/dev-tools"><img src="https://img.shields.io/badge/Dev%20Tools-agentic%20tooling-F28D1A?logo=github&logoColor=white" alt="Repositório do Dev Tools"></a>
  <a href="https://github.com/php-fast-forward/http"><img src="https://img.shields.io/badge/HTTP-PSR--7%2F17%2F18-0F766E?logo=github&logoColor=white" alt="Repositório de HTTP"></a>
</p>

<p align="center">
  <a href="https://github.com/php-fast-forward/event-dispatcher"><img src="https://img.shields.io/badge/Event%20Dispatcher-PSR--14-7C3AED?logo=github&logoColor=white" alt="Repositório do Event Dispatcher"></a>
  <a href="https://github.com/php-fast-forward/container"><img src="https://img.shields.io/badge/Container-PSR--11-2563EB?logo=github&logoColor=white" alt="Repositório do Container"></a>
  <a href="https://github.com/php-fast-forward/fork"><img src="https://img.shields.io/badge/Fork-parallelism-475569?logo=github&logoColor=white" alt="Repositório do Fork"></a>
  <a href="https://github.com/php-fast-forward/enum"><img src="https://img.shields.io/badge/Enum-domain%20objects-0EA5E9?logo=github&logoColor=white" alt="Repositório do Enum"></a>
</p>

## Por que o Fast Forward existe

O PHP Fast Forward está sendo construído para quem quer entregar rápido e manter uma arquitetura que possa evoluir. A proposta é reduzir a dependência do framework, o acoplamento oculto e a configuração repetitiva, preservando limites claros para a aplicação.

Uma boa experiência de desenvolvimento deve vir de padrões úteis, convenções da comunidade e componentes consolidados. O Fast Forward parte de interfaces PSR e composição, apoiando-se em projetos como Symfony, Nyholm, PHP-DI e Laminas, sem exigir um vocabulário novo para cada problema conhecido.

O ecossistema inclui pacotes implementados e repositórios públicos que representam módulos futuros. A missão é construir um ecossistema completo de framework em que você escreva menos classes, configure menos coisas à mão e mantenha o controle dos componentes usados pela aplicação. A existência de um repositório público não significa que o módulo esteja pronto para uso; consulte seu README e suas releases.

<p align="center">
  <img src="../assets/mascot/dash-developer-reading.png" alt="Dash, a raposa do Fast Forward, lendo um livro com seu moletom roxo" width="300">
</p>

## O que ele tem de diferente

- **PSR-first desde o projeto.** HTTP, containers, eventos, relógios, factories e abstrações de cliente usam padrões da comunidade. Cada pacote documenta as interfaces que implementa.
- **Poucas classes, pouca cerimônia.** O metapacote do framework instala a base principal, e um service provider do framework oferece um único ponto de inicialização.
- **Fundamentos componíveis.** Os pacotes cobrem configuração, containers, HTTP, eventos PSR-14, relógios PSR-20, iteradores, criação de processos com fork e mais. Escolha as partes de que sua aplicação precisa.
- **Componentes consolidados.** O Fast Forward se apoia em componentes estabelecidos do PHP e concentra seu próprio código em integração, padrões práticos e fluxo de desenvolvimento.
- **Base para web e eventos.** Os pacotes de HTTP e event-dispatcher disponíveis atendem a requisições/respostas e aplicações orientadas a eventos. Capacidades assíncronas mais amplas fazem parte do roadmap.
- **Bridges e adapters.** Integre ferramentas existentes por meio de contratos claros, para que a aplicação dependa de limites estáveis em vez de espalhar detalhes específicos de um fornecedor pelo código.
- **Fluxos para pessoas e agentes.** O Dev Tools inclui skills reutilizáveis, agentes de projeto, saída estruturada de comandos e automações de repositório que pessoas podem inspecionar e revisar.

## O ecossistema já disponível

- [`fast-forward/framework`](https://github.com/php-fast-forward/framework) reúne a base principal em um único service provider do framework.
- [`fast-forward/dev-tools`](https://github.com/php-fast-forward/dev-tools) padroniza verificações de qualidade, documentação, preparação de repositórios, releases, skills reutilizáveis e fluxos de agentes de projeto.
- [`fast-forward/http`](https://github.com/php-fast-forward/http) oferece uma base HTTP agregada em torno de PSR-7, PSR-17 e PSR-18.
- [`fast-forward/event-dispatcher`](https://github.com/php-fast-forward/event-dispatcher) oferece despacho PSR-14, eventos nomeados, subscribers, prioridades e listeners definidos por atributos.
- [`fast-forward/container`](https://github.com/php-fast-forward/container) agrega containers PSR-11 e service providers.
- [`fast-forward/config`](https://github.com/php-fast-forward/config) carrega e combina fontes de configuração, com cache opcional e suporte a providers.
- [`fast-forward/enum`](https://github.com/php-fast-forward/enum) oferece helpers reutilizáveis para enums, catálogos de domínio e helpers para transições de fluxo.
- [`fast-forward/clock`](https://github.com/php-fast-forward/clock), [`fast-forward/fork`](https://github.com/php-fast-forward/fork) e [`fast-forward/iterators`](https://github.com/php-fast-forward/iterators) oferecem ferramentas específicas para tempo, processos criados com fork e dados iteráveis. O uso de fork exige um ambiente CLI compatível com Unix e suporte a controle de processos.

## Roadmap público

Alguns repositórios públicos descrevem a forma pretendida para o ecossistema antes da implementação dos módulos. O trabalho planejado inclui:

- utilitários de console e uma camada de CLI mais simples para aplicações
- primitivas de agendamento e orquestração de tarefas recorrentes
- recursos de filas e event bus para fluxos assíncronos desacoplados
- mais módulos voltados à aplicação para completar a experiência do framework

A intenção arquitetural continua a mesma: consumir bibliotecas consolidadas por meio de bridges, adapters ou camadas de integração com contratos estáveis do Fast Forward. O objetivo é ajudar as aplicações a manter portabilidade e baixo acoplamento quando a implementação subjacente mudar. Acompanhe cada repositório para saber o estado da implementação e das releases.

## O multiplicador de força: Dev Tools

O [`fast-forward/dev-tools`](https://github.com/php-fast-forward/dev-tools) cuida da manutenção repetitiva compartilhada por muitos repositórios. Funciona como plugin do Composer e como binário local, com resolução de configuração que prioriza o repositório consumidor e preserva suas customizações.

Os repositórios podem sincronizar templates, padrões e convenções operacionais usando o mesmo conjunto de ferramentas. As skills do Fast Forward e os prompts dos agentes de projeto ficam ali junto dos comandos de manutenção.

O Dev Tools oferece:

- geração de documentação e wiki, com workflows compartilhados de build e publicação
- testes, cobertura, métricas, verificações de estilo de código, verificações de PHPDoc e refatoração com Rector
- análise de dependências, prévias de atualização e atualizações assistidas
- criação e validação de changelog, inferência de versão, geração de notas de release e automação de releases
- geração de CODEOWNERS, metadados de financiamento, Git hooks, preparação de repositórios e sincronização de workflows
- agentes de projeto e skills reutilizáveis para pessoas e agentes de IA

Uma lista menor de manutenção deixa menos coisas para o seu eu do futuro lembrar.

## A proposta em um pequeno exemplo

Com PHP 8.3 ou superior, instale o metapacote do framework no seu projeto:

```sh
composer require fast-forward/framework
```

Em seguida, carregue o autoloader do Composer e registre o provider do framework:

```php
<?php

declare(strict_types=1);

use FastForward\Framework\ServiceProvider\FrameworkServiceProvider;

use function FastForward\Container\container;

require __DIR__ . '/vendor/autoload.php';

$container = container(FrameworkServiceProvider::class);
```

Isso inicializa os serviços principais. O [README do framework](https://github.com/php-fast-forward/framework) mostra como obtê-los. Essa é a direção: menos classes, menos trabalho manual de integração, padrões úteis e limites mais claros.

## Construa com a gente

Dash, a raposa do Fast Forward, recebe quem contribui e acompanha a documentação. O [kit de marca e mascote](../assets/README.md) define o personagem, o sistema visual, os assets reutilizáveis e as referências de arte. Use-o ao criar um banner de pacote, uma página de documentação ou uma contribuição para a comunidade.

A [biblioteca visual pública](https://php-fast-forward.github.io/.github/) é o destino no GitHub Pages para versões do logo, poses do mascote e padrões de documentação. As fontes permanecem neste repositório.

Explore a [organização](https://github.com/php-fast-forward), leia os guias dos pacotes, abra uma issue com um exemplo reproduzível ou envie um pull request com escopo claro. Ajude a construir um ecossistema PHP que faça o desenvolvimento avançar e mantenha a arquitetura compreensível.

<p align="center">
  <img src="../assets/mascot/dash-developer-welcome.png" alt="Dash, a raposa do Fast Forward, recebendo quem contribui com seu moletom roxo" width="260">
</p>
