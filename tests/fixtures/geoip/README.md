# GeoIP test database

`GeoIP2-Country-Test.mmdb` is an unmodified MaxMind test database. It contains
selected test records, not a complete database for production.

Source: [MaxMind-DB](https://github.com/maxmind/MaxMind-DB/blob/f4847f28e5d12cd495ad5c1ea297cef9cc45bf4f/test-data/GeoIP2-Country-Test.mmdb),
commit `f4847f28e5d12cd495ad5c1ea297cef9cc45bf4f`.
SHA-256: `b25493e1978dc1a9ade5af1c4cbe175e65fd93bfdfc118ee81f9de1313282fcf`.

The expected records come from the upstream
[source data](https://github.com/maxmind/MaxMind-DB/blob/f4847f28e5d12cd495ad5c1ea297cef9cc45bf4f/source-data/GeoIP2-Country-Test.json):

| Address | Source range | `country.iso_code` | `country.names.en` |
| --- | --- | --- | --- |
| `81.2.69.142` | `81.2.69.142/31` | `GB` | `United Kingdom` |
| `2001:218::` | `2001:218::/32` | `JP` | `Japan` |

The IPv4 record has `registered_country.iso_code` set to `US`. Tests must use
`country`, which describes the location, instead of `registered_country`.

Copyright (c) 2013 - 2026 MaxMind, Inc. The upstream project offers Apache-2.0 or
MIT licensing. This copy uses the [MIT license](LICENSE-MIT), as permitted by the
[upstream license notice](https://github.com/maxmind/MaxMind-DB/blob/f4847f28e5d12cd495ad5c1ea297cef9cc45bf4f/README.md#copyright-and-license).
