# µADR-009: Compiled Code Generation & State-Machine PHP File Dumping
-----
tags: #code-generation #dump-engine #state-machine #opcache #rejected
status: rejected
context: Consideration of compiling route definitions directly into hardcoded executable PHP files (e.g., generated switch/match jump tables) to bypass array lookups and foreach iterations.
reason:
  - Execution profiling demonstrates negligible performance gains (<1-2%). Static route evaluation is already $O(1)$ via native Zend VM opcode array lookups (`FETCH_DIM_R`), while dynamic routes spend over 95% of CPU cycles inside C-level `libpcre2` evaluation regardless of loop mechanics.
  - Disk file dumping introduces runtime I/O dependencies that fail in modern read-only containerized environments (Docker / Kubernetes).
  - Writing executable PHP scripts requires managing complex OPcache invalidation lifecycles (`opcache_invalidate()`) across web-server and CLI execution contexts.
  - Executable PHP dumps destroy data structure flexibility, preventing runtime serialization, APCu shared-memory caching, and CLI inspection.
accepted_alternative:
  - Maintain a strict separation between a pure data-array compiler (`WajhaCompiler`) and an in-memory array consumer (`WajhaDispatcher`).
  - Persist compiled route data structures directly in APCu with infinite retention ($TTL=0$).
consequences:
  - Zero disk I/O operations required during application runtime or compilation phases.
  - Maximum deployment flexibility across read-only containerized environments.
  - Pure, serializable data structures remain fully testable, inspectable, and memory-cacheable.
