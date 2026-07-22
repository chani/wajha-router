# µADR-007: Core 3D Host/Domain Routing
-----
tags: #domain-routing #multitenancy #yagni #anti-bloat #rejected
status: rejected
context: Consideration of incorporating a mandatory 3D host/domain lookup array (`$static[$host][$method][$uri]`) into the core dispatcher.
reason:
  - The majority of web applications and microservices operate in single-domain or reverse-proxy contexts.
  - Structuring core routing tables around mandatory host keys introduces array depth overhead and extra lookup operations for single-domain applications, violating the zero-cost principle.
accepted_alternative:
  - Keep the core dispatcher single-domain focused.
  - Handle domain/subdomain partitioning via outer application orchestration (e.g., multiple dispatcher instances or framework-level middleware).
consequences:
  - Maintains peak execution speed for single-domain applications.
  - Keeps the core router lightweight and free from multi-tenant architectural bloat.
