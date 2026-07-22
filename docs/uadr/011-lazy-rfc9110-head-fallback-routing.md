# µADR-011: Lazy RFC 9110 HEAD Fallback Routing
-----
tags: #http #rfc9110 #head-fallback #memory-footprint #apcu #optimization
status: accepted
context: Mirroring every `GET` route into a dedicated `HEAD` bucket at compile time inflates compiled route data structures and increases APCu RAM consumption by ~40-50%.
decision:
  - Evaluate `HEAD` requests dynamically in `WajhaDispatcher::dispatch()` using an inlined fallback check against existing `GET` route tables upon initial lookup misses.
consequences:
  - Reduces compiled APCu cache memory footprint significantly.
  - Keeps 99%+ of standard `GET` and `POST` request threads running at maximum speed without memory table inflation.
