# PicoDatabase

SQL builder.

- create database object: `new PicoDatabase([host, [username, [password, [database, [encoding]]]]]);`

- basic query: `query('UPDATE table SET field = 1')`

- query with placeholders: `query('UPDATE table SET ?@ = ?_', 'field', '123')`

- possible placeholders:
1. `?_` - parameter will be escaped as value
2. `?@` - parameter will be escaped as field name
3. `?*` - parameter will be inserted as-is
4. `??` - "?" will be inserted

- to start build query: `select()`, `insert()`, `update()`, `delete()`, `replace()` or `buildQuery()`

- to finish query: `execute()` or `fetch[Val/All/Col]`

- simple chain: `select('field')->from('table')->fetchAll()`

- associative arrays are processed like `field => value` or `string_with_placeholder => parameter`

- `set()` is automatically added to insert/update/replace

- example for two previous points: `update('table', array('field' => 1, 'field2 = ?*' => 'NOW()'))->execute()`

- several `where` calls are joined with `AND`: `select('field')->from('table')->where(array('field1' => 1, 'field2' => 2))->where('field3', 3)->fetchAll()`

- each chain call will be translated to SQL operator. Spaces will be added before big letters. `buildQuery()->optimizeTable('table')`
