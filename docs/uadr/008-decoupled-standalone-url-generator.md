# µADR-008: Decoupled Standalone URL Generator
-----
tags: #url-generator #reverse-routing #srp #decoupling #architecture
status: accepted
context: Coupling reverse URL generation logic directly inside `WajhaDispatcher` violates the Single Responsibility Principle and pollutes the inbound dispatching memory footprint.
decision:
  - Keep `WajhaDispatcher` strictly bounded as an inbound request matcher.
  - Export a compiled reverse pattern map from `WajhaCompiler` and encapsulate reverse URL generation in a decoupled, standalone class (`WajhaUrlGenerator`).
consequences:
  - Inbound request dispatching remains 100% unencumbered by URL generation logic.
  - Applications gain reverse URL generation capabilities without paying any runtime memory penalty on inbound requests.
