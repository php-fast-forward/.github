# PHP Fast Forward style

Write so a developer can understand the purpose, choose a useful next step, and verify the result. The voice is clear, welcoming, practical, and quietly confident. It reflects the character described in [SOUL.md](SOUL.md) and the presentation rules in [DESIGN.md](DESIGN.md).

## Language

Public repository documentation, asset descriptions, and reusable product copy use English. In conversation, follow the person's language; use natural Brazilian Portuguese when they write in PT-BR.

The organization profile has an English default at [profile/README.md](profile/README.md), with explicitly supported [Brazilian Portuguese](profile/README.pt-BR.md) and [Spanish](profile/README.es.md) translations. Keep the language links at the top of all three versions, marking the current language. Update their structure, package claims, roadmap status, code, destinations, and image references together; translate the prose and alternative text while preserving identifiers and examples.

Keep code identifiers, namespaces, commands, package names, flags, and output exactly as they appear in the software. Translate the explanation around them. Avoid translating an identifier to make prose sound smoother.

## Names and terminology

| Term | Usage |
| --- | --- |
| PHP Fast Forward | Full framework and public ecosystem name. Use on first mention and when identifying the product. |
| Fast Forward | Short form when the context already identifies PHP Fast Forward. |
| `fast-forward/*` | Composer package namespace. Use exact package names when documenting installation or dependencies. |
| Dash | Mascot name. On first mention, use “Dash, the Fast Forward fox”; in PT-BR, “Dash, a raposa do Fast Forward”; in Spanish, “Dash, el zorro de Fast Forward”. Use Dash afterward. |
| Component / package | A specific reusable unit. Name the actual unit and explain its responsibility. |
| Framework | The shared application-building approach and ecosystem; avoid implying that every package requires the whole ecosystem. |
| PSR-first | A design principle. Name the relevant PSR and the implemented boundary when making a technical claim. |
| Agent-assisted workflow | Work a person can inspect, reproduce, and review with agent support. Describe the actual steps and outputs. |

Use **PHP**, **Composer**, and **PSR** with their established capitalization. Use the exact spelling of upstream projects and credit them where relevant.

## Visual identity in copy

Use the selected F signature or its standalone fox symbol from the [logo kit](assets/README.md#logo-masters). Its outlined Exo 2 lettering and orange “Fast” are graphic treatments; write **PHP Fast Forward** with spaces in prose and preserve exact package identifiers in code. The licensed logo font does not replace the system interface and code fonts.

Describe a local proposal, a selected direction, a prepared export, and a published asset according to its actual state. A concept-generation receipt preserves the decision's source; later selection does not rewrite that historical receipt as publication evidence. New Dash scenes awaiting review remain proposals until their review status changes.

## Voice

Lead with what the reader can do or what changed. Then explain the reason and the evidence they need. Use active verbs and short, connected paragraphs. Define an unfamiliar concept at the point where it matters.

| Situation | Preferred copy | Avoid |
| --- | --- | --- |
| Introduce the framework | “Build with PSR interfaces and compose the components your application needs.” | “The ultimate PHP revolution.” |
| Explain composition | “This example passes the collaborator through the constructor. You can replace it with another implementation.” | “It works by magic.” |
| Describe a small API | “The example uses one class to handle the request. The container supplies its dependencies.” | “Zero complexity, guaranteed.” |
| Welcome a contributor | “Thanks for the example. Please include the PHP version and the command you ran.” | Praise that hides the next action. |
| State uncertainty | “This configuration has not been tested on Windows. The documented check covers Linux.” | A claim of universal portability without supporting checks. |
| Report a fix | “The handler now receives the configured collaborator. The regression test covers a missing binding.” | “Everything is fixed.” |

Examples illustrate tone. Only publish statements that match the implementation and the checks performed.

## Structure and interface copy

For a guide, state the outcome, list necessary prerequisites, show the smallest useful example, and explain how to confirm the result. Add alternatives when they help the reader make a choice.

For a change report, state the behavior that changed, why it changed, and the validation performed. Name material limitations. Keep commands close to the step they support.

Use headings that describe the content, links that name their destination, and button labels that describe an action: “Read the guide”, “Browse packages”, or “View the example”. Avoid vague labels such as “Click here”.

Use tables for comparisons and lists for parallel items or ordered steps. Keep ordinary explanations in prose. Give images meaningful alternative text when they convey information. Decorative mascot images should not repeat adjacent text to screen readers.

## Humor and the fox

Use an occasional short line of developer humor when it fits the moment. Keep the instruction useful if the joke is removed. Never joke at the reader's expense or during an urgent error, security notice, data-loss warning, or serious support incident.

The fox can welcome, guide, learn, and express appreciation. Use its pose intentionally and keep character dialogue brief. Avoid forcing fox puns into every page or making fictional dialogue part of a required technical step.

Example of a light aside: “A smaller dependency graph also gives future-you fewer boxes to unpack.”

## Evidence and claims

“Fast Forward” is the product name. Any claim about speed, memory, reliability, compatibility, or reduced work needs its own support.

- Name the PHP version, package version, environment, and method when publishing measurements.
- Identify the relevant PSR and implementation when claiming standards compatibility.
- Scope portability claims to the environments actually checked.
- Describe the number of classes or steps in a specific example instead of promising universally effortless development.
- Separate a concept, implemented behavior, verified behavior, and a planned feature.
- Cite upstream documentation and licenses when using third-party components or assets.

Use an explicit placeholder for unfinished examples and label proposals as proposals. Do not present a website concept or a mascot variation as a shipped product feature.

## Code, commands, and output

Keep code examples runnable for the documented environment, or clearly label omitted parts. Use syntax-labeled fenced blocks. Keep explanatory prose outside copyable commands and preserve actual output when showing diagnostic evidence.

Do not add mascot artwork, emoji, slogans, or decorative characters to commands, identifiers, JSON, machine-readable output, or copied logs. Essential status information must have a plain-text meaning; color and illustration may reinforce it.

The [asset catalog](assets/README.md) records the available images and their intended use. Follow it when adding the fox to public content.
