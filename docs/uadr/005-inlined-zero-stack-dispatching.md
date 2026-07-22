# µADR-005: Inlined Zero-Stack Dispatching
-----
tags: #dispatcher #inlining #zero-stack #zend-vm #performance
status: accepted
context: Sub-routine method invocations and helper function calls inside the dispatcher hot-path allocate VM stack frames, adding preventable execution latency.
decision:
  - Inline pattern evaluation, variable mapping, and fallback paths directly inside `WajhaDispatcher::dispatch()`.
  - Utilize unrolled array assignments for common variable counts (1 to 2 parameters) to avoid loop overhead.
consequences:
  - Zero function call-frame allocations for matched request threads.
  - Achieves minimum possible CPU cycle overhead during inbound request handling.
