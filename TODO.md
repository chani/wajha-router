# Safi/Wajha Router - Backlog

## To Evaluate

- [ ] **Native PCRE Optional Segment Compilation**
  - Evaluate compiling optional path segments (`[/...]`) directly into native PCRE non-capturing optional groups `(?:...)?` inside dynamic chunks instead of combinatorial route expansion.
  - Assess memory footprint savings during compilation versus potential runtime PCRE evaluation impact for deeply nested optional routes.

- [ ] **Secondary Bucketing for Root-Variable Routes**
  - Evaluate alternative bucketing strategies for paths starting with dynamic segments (e.g., `/{userId}/...`) to avoid fallback to the generic `'*'` bucket.
  - Explore segment count/depth partitioning or static prefix extraction following the dynamic parameter to maintain high throughput in variable-heavy URL structures.

- [ ] **Domain & Subdomain Routing**
  - Evaluate support for host-based match constraints (e.g., `{subdomain}.example.com`).
  - Assess memory and compile-time pattern complexity impact for multi-domain applications.

- [ ] **Reverse Route Generation (URL Building)**
  - Evaluate named route indexing during compile phase (`$compiler->get('/users/{id}', 'UserShow')->name('users.show')`).
  - Implement zero-overhead URL synthesis via parameter interpolation (`$router->generate('users.show', ['id' => 42])`).

- [ ] **Strict CORS & OPTIONS Handling**
  - Evaluate strict runtime assertions for unhandled `OPTIONS` preflight requests.
  - Enforce immediate runtime error or explicit RFC 9110 HTTP 405 fallback when required `Allow` header arrays cannot be derived.
