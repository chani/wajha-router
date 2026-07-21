# Safi/Wajha Router - Backlog

## To Evaluate

- [ ] **Domain & Subdomain Routing**
  - Evaluate support for host-based match constraints (e.g., `{subdomain}.example.com`).
  - Assess memory and compile-time pattern complexity impact for multi-domain applications.

- [ ] **Reverse Route Generation (URL Building)**
  - Evaluate named route indexing during compile phase (`$compiler->get('/users/{id}', 'UserShow')->name('users.show')`).
  - Implement zero-overhead URL synthesis via parameter interpolation (`$router->generate('users.show', ['id' => 42])`).

- [ ] **Strict CORS & OPTIONS Handling**
  - Evaluate strict runtime assertions for unhandled `OPTIONS` preflight requests.
  - Enforce immediate runtime error or explicit RFC 9110 HTTP 405 fallback when required `Allow` header arrays cannot be derived.
