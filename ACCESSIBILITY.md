# PHP Fast Forward accessibility priorities

Make PHP Fast Forward documentation, websites, examples, and interfaces usable by people with different access needs. These are organization priorities; follow any more specific guidance in the affected repository.

## Design and implementation

- Use readable text and sufficient contrast for text, controls, and focus indicators. Convey status and meaning with text or other cues alongside color.
- Keep instructions and code selectable and copyable as text. Provide textual explanations for diagrams and screenshots when they carry information.
- Make interactive elements operable by keyboard, with visible focus and a logical navigation order. Use meaningful labels and semantic structure.
- Preserve readable content when text is enlarged or the viewport changes. Give useful alternative text to informative images and keep purely decorative images out of the reading flow.

Use [W3C WAI's design guidance](https://www.w3.org/WAI/tips/designing/) when reviewing contrast, navigation, labels, structure, and image alternatives.

Keep nonessential animation optional. For web interfaces, respect reduced-motion preferences and provide a static equivalent; [WAI technique C39](https://www.w3.org/WAI/WCAG22/Techniques/css/C39) explains the `prefers-reduced-motion` approach. A mascot animation must not be required to understand an instruction or status.

## Review and reporting

Review relevant changes with keyboard navigation, text enlargement, contrast checks, and appropriate assistive technology or content checks. Record the surface and environment tested, observed barriers, and unresolved limitations. A compliance statement should identify the standard, assessed scope, and supporting evidence.

Report an accessibility barrier as a bug in the affected repository. Include the page or component, expected access, reproduction steps, and relevant browser or assistive technology version when useful. Keep the report public-safe; you do not need to disclose a disability or personal medical information.

For central profile content, mascot assets, or the visual system, use [the profile and design repository](https://github.com/php-fast-forward/.github/issues) and its brand form. Its [design guidance](https://github.com/php-fast-forward/.github/blob/main/design.md) and [asset catalog](https://github.com/php-fast-forward/.github/blob/main/assets/README.md) document intended applications and known asset limitations.
