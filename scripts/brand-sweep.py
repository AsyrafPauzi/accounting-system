#!/usr/bin/env python3
"""
BukuCloud brand-token sweep.

Walks resources/js/Pages and resources/js/Components and replaces legacy
Tailwind colour tokens (indigo, violet, blue, slate, gray, rose, emerald,
amber, sky, etc.) with brand tokens (cream, surface, surface-alt, ink,
ink-muted, border-warm, terracotta, forest, mustard).

Run from the project root. Prints a per-file change count.

Idempotent — safe to re-run; rules are anchored on the legacy tokens which
disappear after the first pass.
"""
import os
import re

ROOT = os.path.join(os.path.dirname(__file__), '..')
PAGES = os.path.normpath(os.path.join(ROOT, 'resources/js/Pages'))
COMPONENTS = os.path.normpath(os.path.join(ROOT, 'resources/js/Components'))

# Match a Tailwind shade and tone-classify it. Uses an explicit, ordered
# alternation (longest first) so that "500" matches before "50".
LIGHT = r'(?:200|300|100|50)'      # very light shades
LOW = r'(?:400|300|200|100|50)'    # low/muted
MID = r'(?:500|400|300|200|100|50)'  # mid+light combined
MIDISH = r'(?:700|600|500|400|300|200|100|50)'
HEAVY = r'(?:950|900|800|700|600)'  # heavy/dark
ALL = r'(?:950|900|800|700|600|500|400|300|200|100|50)'

# Trailing boundary that prevents "50" from matching the prefix of "500" —
# (?!\d) asserts the next char is not a digit. Critical for correctness.
B = r'(?!\d)'

INDIGO_FAMILY = r'(?:indigo|violet|blue|sky)'
EMERALD_FAMILY = r'(?:emerald|green|teal|lime)'
ROSE_FAMILY = r'(?:rose|pink)'
AMBER_FAMILY = r'(?:amber|yellow|orange)'
NEUTRAL_FAMILY = r'(?:slate|gray|zinc|neutral|stone)'

VARIANT = r'(hover:|focus:|active:|group-hover:|dark:|md:|lg:|sm:|xl:|focus-visible:|focus-within:)?'


# Each tuple is (regex pattern, replacement). Patterns are applied in sequence.
RULES = [
    # ---- Gradient backgrounds collapse to solid brand ----
    (r'bg-gradient-to-(?:br|r|tr|t|tl|l|bl|b)\s+from-' + INDIGO_FAMILY + r'-\d+(?:/\d+)?(?:\s+via-(?:indigo|violet|blue|sky|fuchsia|purple)-\d+(?:/\d+)?)?\s+to-(?:indigo|violet|blue|sky|fuchsia|purple)-\d+(?:/\d+)?', 'bg-terracotta'),
    (r'bg-gradient-to-(?:br|r|tr|t|tl|l|bl|b)\s+from-' + EMERALD_FAMILY + r'-\d+(?:/\d+)?(?:\s+via-' + EMERALD_FAMILY + r'-\d+(?:/\d+)?)?\s+to-' + EMERALD_FAMILY + r'-\d+(?:/\d+)?', 'bg-forest'),
    (r'bg-gradient-to-(?:br|r|tr|t|tl|l|bl|b)\s+from-(?:rose|red|pink)-\d+(?:/\d+)?(?:\s+via-(?:rose|red|pink|orange)-\d+(?:/\d+)?)?\s+to-(?:rose|red|pink|orange)-\d+(?:/\d+)?', 'bg-terracotta'),
    (r'bg-gradient-to-(?:br|r|tr|t|tl|l|bl|b)\s+from-' + AMBER_FAMILY + r'-\d+(?:/\d+)?(?:\s+via-' + AMBER_FAMILY + r'-\d+(?:/\d+)?)?\s+to-' + AMBER_FAMILY + r'-\d+(?:/\d+)?', 'bg-mustard'),
    (r'bg-gradient-to-(?:br|r|tr|t|tl|l|bl|b)\s+from-' + NEUTRAL_FAMILY + r'-\d+(?:/\d+)?(?:\s+via-' + NEUTRAL_FAMILY + r'-\d+(?:/\d+)?)?\s+to-' + NEUTRAL_FAMILY + r'-\d+(?:/\d+)?', 'bg-cream'),

    # ---- Coloured shadows — drop the colour tint ----
    (r'shadow-(?:indigo|violet|blue|sky|emerald|green|teal|lime|rose|red|pink|amber|yellow|orange|slate|gray|zinc|neutral|stone)-\d+(?:/\d+)?', ''),

    # ---- Coloured rings ----
    (VARIANT + r'ring-' + INDIGO_FAMILY + r'-' + ALL + B + r'(/\d+)?', r'\1ring-terracotta\2'),
    (VARIANT + r'ring-' + EMERALD_FAMILY + r'-' + ALL + B + r'(/\d+)?', r'\1ring-forest\2'),
    (VARIANT + r'ring-' + ROSE_FAMILY + r'-' + ALL + B + r'(/\d+)?', r'\1ring-terracotta\2'),
    (VARIANT + r'ring-' + AMBER_FAMILY + r'-' + ALL + B + r'(/\d+)?', r'\1ring-mustard\2'),
    (VARIANT + r'ring-' + NEUTRAL_FAMILY + r'-' + ALL + B + r'(/\d+)?', r'\1ring-border-warm\2'),

    # ---- Indigo / violet / blue / sky → terracotta family ----
    (VARIANT + r'bg-' + INDIGO_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1bg-surface-alt\2'),
    (VARIANT + r'bg-' + INDIGO_FAMILY + r'-(?:700|600|500|400|300)' + B + r'(/\d+)?', r'\1bg-terracotta\2'),
    (VARIANT + r'bg-' + INDIGO_FAMILY + r'-(?:950|900|800)' + B + r'(/\d+)?', r'\1bg-terracotta-dark\2'),

    (VARIANT + r'text-' + INDIGO_FAMILY + r'-' + MIDISH + B, r'\1text-terracotta'),
    (VARIANT + r'text-' + INDIGO_FAMILY + r'-(?:950|900|800)' + B, r'\1text-ink'),

    (VARIANT + r'border-' + INDIGO_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1border-border-warm\2'),
    (VARIANT + r'border-' + INDIGO_FAMILY + r'-(?:950|900|800|700|600|500|400)' + B + r'(/\d+)?', r'\1border-terracotta\2'),

    # ---- Emerald / green / teal / lime → forest ----
    (VARIANT + r'bg-' + EMERALD_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1bg-forest/10\2'),
    (VARIANT + r'bg-' + EMERALD_FAMILY + r'-(?:950|900|800|700|600|500|400)' + B + r'(/\d+)?', r'\1bg-forest\2'),
    (VARIANT + r'text-' + EMERALD_FAMILY + r'-' + MIDISH + B, r'\1text-forest'),
    (VARIANT + r'text-' + EMERALD_FAMILY + r'-(?:950|900|800)' + B, r'\1text-forest-dark'),
    (VARIANT + r'border-' + EMERALD_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1border-forest/30\2'),
    (VARIANT + r'border-' + EMERALD_FAMILY + r'-(?:950|900|800|700|600|500|400)' + B + r'(/\d+)?', r'\1border-forest\2'),

    # ---- Rose / pink → terracotta (red kept for DangerButton's destructive semantic) ----
    (VARIANT + r'bg-' + ROSE_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1bg-terracotta/10\2'),
    (VARIANT + r'bg-' + ROSE_FAMILY + r'-(?:950|900|800|700|600|500|400)' + B + r'(/\d+)?', r'\1bg-terracotta\2'),
    (VARIANT + r'text-' + ROSE_FAMILY + r'-' + ALL + B, r'\1text-terracotta'),
    (VARIANT + r'border-' + ROSE_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1border-terracotta/30\2'),
    (VARIANT + r'border-' + ROSE_FAMILY + r'-(?:950|900|800|700|600|500|400)' + B + r'(/\d+)?', r'\1border-terracotta\2'),

    # ---- Amber / yellow / orange → mustard ----
    (VARIANT + r'bg-' + AMBER_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1bg-mustard/15\2'),
    (VARIANT + r'bg-' + AMBER_FAMILY + r'-(?:950|900|800|700|600|500|400)' + B + r'(/\d+)?', r'\1bg-mustard\2'),
    (VARIANT + r'text-' + AMBER_FAMILY + r'-' + MIDISH + B, r'\1text-mustard'),
    (VARIANT + r'text-' + AMBER_FAMILY + r'-(?:950|900|800)' + B, r'\1text-ink'),
    (VARIANT + r'border-' + AMBER_FAMILY + r'-' + LIGHT + B + r'(/\d+)?', r'\1border-mustard/40\2'),
    (VARIANT + r'border-' + AMBER_FAMILY + r'-(?:950|900|800|700|600|500|400)' + B + r'(/\d+)?', r'\1border-mustard\2'),

    # ---- Slate / gray / zinc / neutral / stone → ink + surface ----
    (VARIANT + r'bg-' + NEUTRAL_FAMILY + r'-50' + B + r'(/\d+)?', r'\1bg-cream\2'),
    (VARIANT + r'bg-' + NEUTRAL_FAMILY + r'-(?:200|100)' + B + r'(/\d+)?', r'\1bg-surface-alt\2'),
    (VARIANT + r'bg-' + NEUTRAL_FAMILY + r'-(?:950|900|800|700|600|500|400|300)' + B + r'(/\d+)?', r'\1bg-ink\2'),
    (VARIANT + r'bg-white' + B + r'(/\d+)?', r'\1bg-surface\2'),
    (VARIANT + r'bg-black' + B + r'(/\d+)?', r'\1bg-ink\2'),

    (VARIANT + r'text-' + NEUTRAL_FAMILY + r'-(?:500|400|300|200|100|50)' + B, r'\1text-ink-muted'),
    (VARIANT + r'text-' + NEUTRAL_FAMILY + r'-(?:950|900|800|700|600)' + B, r'\1text-ink'),

    (VARIANT + r'border-' + NEUTRAL_FAMILY + r'-(?:400|300|200|100|50)' + B + r'(/\d+)?', r'\1border-border-warm\2'),
    (VARIANT + r'border-' + NEUTRAL_FAMILY + r'-(?:950|900|800|700|600|500)' + B + r'(/\d+)?', r'\1border-ink\2'),

    (r'placeholder-' + NEUTRAL_FAMILY + r'-(?:500|400|300)' + B, 'placeholder-ink-muted/60'),
    (r'placeholder-' + NEUTRAL_FAMILY + r'-(?:200|100|50)' + B, 'placeholder-ink-muted/40'),

    # ---- divide-* colour tokens ----
    (r'divide-' + NEUTRAL_FAMILY + r'-(?:300|200|100|50)' + B + r'(/\d+)?', r'divide-border-warm\1'),
    (r'divide-' + INDIGO_FAMILY + r'-(?:300|200|100|50)' + B + r'(/\d+)?', r'divide-border-warm\1'),

    # ---- Headings: bold + brand ink → display + medium ink ----
    (r'\bfont-bold\s+text-ink\b', 'font-display font-medium text-ink'),
    (r'\bfont-black\s+text-ink\b', 'font-display font-semibold text-ink'),
]

EXCLUDE_NAMES = {
    'DangerButton.jsx',  # red kept on purpose for destructive semantics
}


def transform(text):
    original = text
    count = 0
    for pattern, replacement in RULES:
        new_text, n = re.subn(pattern, replacement, text)
        if n:
            count += n
            text = new_text
    return text, count if text != original else 0


def walk(root):
    for base, dirs, files in os.walk(root):
        for fn in files:
            if not fn.endswith(('.jsx', '.js')):
                continue
            if fn in EXCLUDE_NAMES:
                continue
            yield os.path.join(base, fn)


def main():
    targets = []
    for root in (PAGES, COMPONENTS):
        targets.extend(walk(root))

    total = 0
    files_changed = 0
    for path in sorted(targets):
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
        new_content, changes = transform(content)
        if changes and new_content != content:
            with open(path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f'  {changes:>4} replacements  {os.path.relpath(path, ROOT)}')
            total += changes
            files_changed += 1

    print(f'\nDone. {files_changed} files changed, {total} replacements total.')


if __name__ == '__main__':
    main()
