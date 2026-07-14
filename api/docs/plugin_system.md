# Intelligent QA Plugin System

The plugin system gives DeepThink and Agent controlled access to TE-KG evidence.
Plugins are tools, not answer writers.

## Source Of Truth

- Registry: `api/agent/plugin_registry.php`
- Planner-facing catalog: `api/agent/plugins/PLUGIN_CATALOG.md`
- Maintainer notes: `api/agent/plugins/README.md`
- Plugin implementations: `api/agent/plugins/*.php`

`tekg_agent_plugin_directory()` reads the catalog and supplies it to LLM planning
or collection prompts. The catalog should therefore be concise, accurate, and
safe for model routing.

## Registered Plugins

- Entity Resolver
- Site Navigator Plugin
- Graph Plugin
- Graph Analytics Plugin
- Cypher Explorer Plugin
- Literature Plugin
- Literature Reading Plugin
- Tree Plugin
- Expression Plugin
- Genome Plugin
- Sequence Plugin
- Citation Resolver

## Routing Rules

- Call plugins only when the question or evidence gap requires them.
- Do not call many plugins speculatively.
- Call each plugin at most once per run unless a future plan explicitly changes
  that contract.
- Entity Resolver is bootstrap-only.
- Citation Resolver is post-processing-only.
- Site Navigator Plugin returns navigation routes only; it is not scientific
  evidence.
- Literature Reading Plugin requires usable Literature Plugin results.

## Evidence Boundaries

- Graph edges are local structured associations and relation labels. They do not
  prove biological causality by themselves.
- Graph Analytics metrics describe current graph contents, not biological
  importance.
- Literature metadata and PubMed retrieval do not guarantee claim support.
- Expression Plugin output summarizes available expression runtime data;
  runtime failure is not biological absence.
- Genome Plugin representative loci are examples unless the payload explicitly
  supports completeness.
- Sequence Plugin records support sequence facts, but structure hints should not
  be promoted into unsupported classification claims.
- Citation Resolver normalizes citations; it does not audit whether a citation
  supports a claim.

## Maintaining The Catalog

When adding or changing a plugin:

1. Update `api/agent/plugin_registry.php`.
2. Update `api/agent/plugins/PLUGIN_CATALOG.md` with purpose, use cases, input
   requirements, and evidence boundary.
3. Update `api/agent/plugins/README.md` if the interface, output shape, or
   maintainer contract changes.
4. Add or update plugin tests and frontend event checks when user-visible plugin
   events change.

Do not make the catalog a long manual. It is consumed by LLM planners and should
remain routing-oriented.
