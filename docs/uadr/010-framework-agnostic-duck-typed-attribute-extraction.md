# µADR-010: Framework-Agnostic Duck-Typed Attribute Extraction
-----
tags: #attributes #duck-typing #decoupling #architecture #zero-lock-in
status: accepted
context: Requiring consuming applications to import a concrete Wajha `#[Route]` attribute class creates unwanted framework lock-in and vendor coupling inside domain controllers.
decision:
  - Implement duck-typing via `property_exists()` inspection inside `WajhaAttributeLoader` rather than type-hinting a fixed attribute class.
  - Read any attribute object exposing `path` and `method` properties dynamically.
consequences:
  - Zero vendor lock-in for application controllers.
  - Consuming frameworks (such as Safi) can utilize their own native `#[Route]` attribute definitions seamlessly.
