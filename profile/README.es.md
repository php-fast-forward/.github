<p align="center">
  <a href="README.md" lang="en">English</a> · <a href="README.pt-BR.md" lang="pt-BR">Português (Brasil)</a> · <strong>Español</strong>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="../assets/brand/fast-forward-logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="../assets/brand/fast-forward-logo.svg">
    <img src="../assets/brand/fast-forward-logo.svg" alt="Logotipo de PHP Fast Forward" width="640">
  </picture>
</p>

<p align="center">
  <strong>El framework PHP para quienes quieren desarrollar con agilidad sin renunciar a la arquitectura.</strong>
</p>

<p align="center">
  PSR-first • componible • orientado a eventos • personas + agentes
</p>

<p align="center">
  <a href="https://github.com/php-fast-forward/framework"><img src="https://img.shields.io/badge/Framework-core-1E293B?logo=github&logoColor=white" alt="Repositorio del Framework"></a>
  <a href="https://github.com/php-fast-forward/dev-tools"><img src="https://img.shields.io/badge/Dev%20Tools-agentic%20tooling-F28D1A?logo=github&logoColor=white" alt="Repositorio de Dev Tools"></a>
  <a href="https://github.com/php-fast-forward/http"><img src="https://img.shields.io/badge/HTTP-PSR--7%2F17%2F18-0F766E?logo=github&logoColor=white" alt="Repositorio de HTTP"></a>
</p>

<p align="center">
  <a href="https://github.com/php-fast-forward/event-dispatcher"><img src="https://img.shields.io/badge/Event%20Dispatcher-PSR--14-7C3AED?logo=github&logoColor=white" alt="Repositorio de Event Dispatcher"></a>
  <a href="https://github.com/php-fast-forward/container"><img src="https://img.shields.io/badge/Container-PSR--11-2563EB?logo=github&logoColor=white" alt="Repositorio de Container"></a>
  <a href="https://github.com/php-fast-forward/fork"><img src="https://img.shields.io/badge/Fork-parallelism-475569?logo=github&logoColor=white" alt="Repositorio de Fork"></a>
  <a href="https://github.com/php-fast-forward/enum"><img src="https://img.shields.io/badge/Enum-domain%20objects-0EA5E9?logo=github&logoColor=white" alt="Repositorio de Enum"></a>
</p>

## Por qué existe Fast Forward

PHP Fast Forward se está construyendo para quienes quieren entregar rápido y conservar una arquitectura que puedan evolucionar. La propuesta es reducir la dependencia del framework, el acoplamiento oculto y la configuración repetitiva, manteniendo límites claros para la aplicación.

Una buena experiencia de desarrollo debe surgir de valores predeterminados útiles, estándares de la comunidad y componentes consolidados. Fast Forward parte de interfaces PSR y composición, apoyándose en proyectos como Symfony, Nyholm, PHP-DI y Laminas, sin exigir un vocabulario nuevo para cada problema conocido.

El ecosistema incluye paquetes implementados y repositorios públicos que representan módulos futuros. La misión es construir un ecosistema completo de framework donde escribas menos clases, configures menos cosas a mano y mantengas el control de los componentes que usa tu aplicación. La existencia de un repositorio público no significa que el módulo esté listo para usar; consulta su README y sus releases.

<p align="center">
  <img src="../assets/mascot/dash-developer-reading.png" alt="Dash, el zorro de Fast Forward, leyendo un libro con su sudadera morada" width="300">
</p>

## Qué lo hace diferente

- **PSR-first desde el diseño.** HTTP, contenedores, eventos, relojes, factories y abstracciones de cliente usan estándares de la comunidad. Cada paquete documenta las interfaces que implementa.
- **Pocas clases, poca ceremonia.** El metapaquete del framework instala la base principal, y un service provider del framework ofrece un único punto de inicialización.
- **Fundamentos componibles.** Los paquetes cubren configuración, contenedores, HTTP, eventos PSR-14, relojes PSR-20, iteradores, creación de procesos con fork y más. Elige las partes que necesita tu aplicación.
- **Componentes consolidados.** Fast Forward se apoya en componentes establecidos de PHP y concentra su propio código en integración, valores predeterminados prácticos y flujo de desarrollo.
- **Base para web y eventos.** Los paquetes de HTTP y event-dispatcher disponibles cubren solicitudes/respuestas y aplicaciones orientadas a eventos. Las capacidades asíncronas más amplias forman parte de la hoja de ruta.
- **Bridges y adapters.** Integra herramientas existentes mediante contratos claros, para que la aplicación dependa de límites estables en lugar de repartir detalles específicos de un proveedor por todo el código.
- **Flujos para personas y agentes.** Dev Tools incluye skills reutilizables, agentes de proyecto, salida estructurada de comandos y automatizaciones de repositorio que las personas pueden inspeccionar y revisar.

## El ecosistema ya disponible

- [`fast-forward/framework`](https://github.com/php-fast-forward/framework) reúne la base principal en un único service provider del framework.
- [`fast-forward/dev-tools`](https://github.com/php-fast-forward/dev-tools) estandariza controles de calidad, documentación, preparación de repositorios, releases, skills reutilizables y flujos de agentes de proyecto.
- [`fast-forward/http`](https://github.com/php-fast-forward/http) ofrece una base HTTP agregada en torno a PSR-7, PSR-17 y PSR-18.
- [`fast-forward/event-dispatcher`](https://github.com/php-fast-forward/event-dispatcher) ofrece despacho PSR-14, eventos con nombre, subscribers, prioridades y listeners definidos mediante atributos.
- [`fast-forward/container`](https://github.com/php-fast-forward/container) agrega contenedores PSR-11 y service providers.
- [`fast-forward/config`](https://github.com/php-fast-forward/config) carga y combina fuentes de configuración, con caché opcional y soporte para providers.
- [`fast-forward/enum`](https://github.com/php-fast-forward/enum) ofrece helpers reutilizables para enums, catálogos de dominio y helpers para transiciones de flujo.
- [`fast-forward/clock`](https://github.com/php-fast-forward/clock), [`fast-forward/fork`](https://github.com/php-fast-forward/fork) y [`fast-forward/iterators`](https://github.com/php-fast-forward/iterators) ofrecen herramientas específicas para tiempo, procesos creados con fork y datos iterables. El uso de fork requiere un entorno CLI compatible con Unix y soporte para control de procesos.

## Hoja de ruta pública

Algunos repositorios públicos describen la forma prevista del ecosistema antes de implementar sus módulos. El trabajo planificado incluye:

- utilidades de consola y una capa de CLI más sencilla para aplicaciones
- primitivas de planificación y orquestación de tareas recurrentes
- capacidades de colas y event bus para flujos asíncronos desacoplados
- más módulos orientados a la aplicación para completar la experiencia del framework

La intención arquitectónica sigue siendo la misma: consumir bibliotecas consolidadas mediante bridges, adapters o capas de integración con contratos estables de Fast Forward. El objetivo es ayudar a las aplicaciones a mantener la portabilidad y un bajo acoplamiento cuando cambie la implementación subyacente. Sigue cada repositorio para conocer el estado de su implementación y sus releases.

## El multiplicador de fuerza: Dev Tools

[`fast-forward/dev-tools`](https://github.com/php-fast-forward/dev-tools) se ocupa del mantenimiento repetitivo compartido por muchos repositorios. Funciona como plugin de Composer y como binario local, con una resolución de configuración que prioriza el repositorio consumidor y conserva sus personalizaciones.

Los repositorios pueden sincronizar plantillas, valores predeterminados y convenciones operativas mediante el mismo conjunto de herramientas. Las skills de Fast Forward y los prompts de los agentes de proyecto se encuentran allí junto a los comandos de mantenimiento.

Dev Tools ofrece:

- generación de documentación y wiki, con workflows compartidos de build y publicación
- pruebas, cobertura, métricas, controles de estilo de código, controles de PHPDoc y refactorización con Rector
- análisis de dependencias, vistas previas de actualización y actualizaciones asistidas
- creación y validación de changelog, inferencia de versión, generación de notas de release y automatización de releases
- generación de CODEOWNERS, metadatos de financiación, Git hooks, preparación de repositorios y sincronización de workflows
- agentes de proyecto y skills reutilizables para personas y agentes de IA

Una lista de mantenimiento más corta deja menos cosas que recordar a tu yo del futuro.

## La propuesta en un pequeño ejemplo

Con PHP 8.3 o superior, instala el metapaquete del framework en tu proyecto:

```sh
composer require fast-forward/framework
```

Después, carga el autoloader de Composer y registra el provider del framework:

```php
<?php

declare(strict_types=1);

use FastForward\Framework\ServiceProvider\FrameworkServiceProvider;

use function FastForward\Container\container;

require __DIR__ . '/vendor/autoload.php';

$container = container(FrameworkServiceProvider::class);
```

Esto inicializa los servicios principales. El [README del framework](https://github.com/php-fast-forward/framework) muestra cómo obtenerlos. Esa es la dirección: menos clases, menos trabajo manual de integración, valores predeterminados útiles y límites más claros.

## Construye con nosotros

Dash, el zorro de Fast Forward, recibe a quienes contribuyen y acompaña la documentación. El [kit de marca y mascota](../assets/README.md) define el personaje, el sistema visual, los assets reutilizables y las referencias de arte. Úsalo al crear un banner de paquete, una página de documentación o una contribución para la comunidad.

La [biblioteca visual pública](https://php-fast-forward.github.io/.github/) es el destino en GitHub Pages para variantes del logotipo, poses de la mascota y patrones de documentación. Sus fuentes permanecen en este repositorio.

Explora la [organización](https://github.com/php-fast-forward), lee las guías de los paquetes, abre una issue con un ejemplo reproducible o envía un pull request con un alcance claro. Ayuda a construir un ecosistema PHP que haga avanzar el desarrollo y mantenga la arquitectura comprensible.

<p align="center">
  <img src="../assets/mascot/dash-developer-welcome.png" alt="Dash, el zorro de Fast Forward, dando la bienvenida a quienes contribuyen con su sudadera morada" width="260">
</p>
