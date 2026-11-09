# Contributing

Moved out of the README; nothing was removed.

```bash
composer install
php tests/run.php            # PHP contracts (wire snapshots, validation, enums)
vendor/bin/phpstan analyse   # level max
vendor/bin/pam-native-plugin validate pam-native.plugin.json
```

Keep Android and iOS renderers in parity: a new command kind is appended to `CanvasCommandKind`,
`pam-native.idl.json`, both renderers and `docs/commands.md`, with a snapshot test in `tests/run.php`.
