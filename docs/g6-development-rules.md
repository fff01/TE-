# G6 Development Rules

## Goal

Develop G6 without silently damaging the existing Cytoscape-based site.

## Core Principle

Before the Cyt/G6 switcher is officially introduced, G6 must be treated as a parallel system, not as a branch inside the current Cyt default entry.

## Red Lines

1. Do not directly wire G6 into the current default entry pages.
2. Do not add more G6 branching logic into Cyt shared entry files.
3. Do not let G6 initialization depend on Cyt initialization.
4. Every G6 change must be followed by a Cyt regression check.

## Files Considered Cyt Stable Zone

These files are the Cyt stable entry and should not be modified for normal G6 feature work unless we are explicitly working on the renderer switcher:

- `index.php`
- `preview.php`
- `index_demo.html`
- `assets/js/index_demo.graph.js`
- `assets/js/index_demo.qa.js`

## Recommended G6 Development Path

1. Build G6 in an independent page or entry.
2. Keep G6 renderer logic inside G6-only files.
3. Share data, API results, and text resources when needed.
4. Do not share initialization flow between Cyt and G6 before the switcher stage.

## Architecture Rule

Keep these three layers separate:

- Shared data layer
- Cyt renderer / entry layer
- G6 renderer / entry layer

Shared layers may provide:

- graph data
- tree data
- API payloads
- terminology
- descriptions
- UI text

Shared layers must not directly control:

- Cyt instance lifecycle
- G6 instance lifecycle
- renderer-specific event chains

## Event and Global Variable Rule

Do not use one shared runtime graph instance for both engines.

Allowed pattern:

- Cyt uses Cyt-only globals
- G6 uses G6-only globals

Avoid cross-calling:

- G6 should not call Cyt instance methods
- Cyt should not call G6 instance methods

## Page Loading Rule

Before the official switcher exists:

- Cyt pages load only Cyt runtime
- G6 pages load only G6 runtime

Do not build a mixed default page that loads both and decides later unless we are explicitly implementing the switcher.

## Feature Development Rule

For any new G6 feature:

1. Make it work in an isolated G6 page first.
2. Make it stable there.
3. Only then consider integration.

This applies to:

- default tree
- dynamic graph
- search sync
- QA sync
- focus
- fixed view
- reset

## Visual Tuning Rule

When tuning G6 visuals, only modify G6 renderer files.

Do not touch Cyt shared entry files just to adjust:

- node size
- combo style
- labels
- animation
- plugin behavior

## Git Rule

Every stable stage should be committed and pushed before the next risky G6 step.

Recommended milestones:

1. Cyt stable baseline
2. G6 default tree stable
3. G6 dynamic graph stable
4. G6/Cyt switcher stable

## Mandatory Cyt Regression Checklist

After every G6 change, verify:

1. Home page preview loads on first visit
2. Preview page loads on first visit
3. Cyt default tree works
4. Cyt node click still jumps correctly
5. Cyt search still works
6. Cyt QA still works
7. Cyt reset / focus / fixed view still works

## Integration Rule

Only after both are independently stable:

1. G6 default tree stable
2. G6 dynamic graph stable
3. Cyt stable baseline preserved

Then begin Cyt/G6 switcher work.

## Final Rule

If there is ever a tradeoff between preserving Cyt stability and making G6 progress fast, preserve Cyt stability first.
