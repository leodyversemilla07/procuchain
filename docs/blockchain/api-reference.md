# MultiChain JSON-RPC API Commands

## Controlling MultiChain from the Command Line or Your Application

### Accessing the API

To access MultiChain’s API, use the included `multichain-cli` command-line tool, a JSON-RPC client with basic access authentication, or one of many official or third-party libraries.

-   **API Credentials**: Stored in `~/.multichain/[chain-name]/multichain.conf`, randomly generated on first `multichaind` call for that chain.

    -   Path customizable with `-datadir=` option.
    -   Windows equivalent: `%APPDATA%\MultiChain\`.

-   **Single Command Usage**:

    ```
    multichain-cli [chain-name] [command] [parameters...]
    ```

-   **Interactive Mode (Linux)**:
    ```
    multichain-cli [chain-name]
    ```
    -   Enter commands as `[command] [parameters...]`.
    -   Exit with `bye`, `exit`, `quit`, or `Control-D`.

---

## List of API Commands by Category

All optional parameters are in `(round brackets)` with defaults after `=`. MultiChain Enterprise features are **highlighted**. See [error codes](#error-codes-and-messages) for details.

### General Utilities

| Command               | Parameters                                    | Description                                                                                               |
| --------------------- | --------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| `getblockchainparams` | `(display-names=true)` `(with-upgrades=true)` | Returns blockchain parameters. Use `display-names` for readable names, `with-upgrades` for latest params. |
| `gethealthcheck`      |                                               | **Enterprise**: Retrieves node health check. Available on JSON-RPC or separate health checking port.      |
| `getruntimeparams`    |                                               | Returns node runtime parameters, some modifiable with `setruntimeparam`.                                  |
| `setruntimeparam`     | `param value`                                 | Sets and applies runtime parameter `param` to `value`. See supported parameters in docs.                  |
| `getinfo`             |                                               | General info about node and blockchain, including chain name, burn address, and setup phase length.       |
| `getinitstatus`       |                                               | Returns node initialization status during new blockchain connection.                                      |
| `help`                |                                               | Lists available API commands, including MultiChain-specific ones.                                         |
| `stop`                |                                               | Shuts down the blockchain node (`multichaind` process).                                                   |

---

### Managing Wallet Addresses

| Command              | Parameters                                                 | Description                                                                          |
| -------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `addmultisigaddress` | `nrequired ["key", ...]`                                   | Creates P2SH multisig address requiring `nrequired` signatures from provided keys.   |
| `getaddresses`       | `(verbose=false)`                                          | Lists wallet addresses. Use `verbose=true` for detailed info like `validateaddress`. |
| `getnewaddress`      |                                                            | Generates a new address with private key added to wallet.                            |
| `importaddress`      | `address(es) (label) (rescan=true)`                        | Adds watch-only address(es) to wallet. `rescan` controls blockchain rescan scope.    |
| `listaddresses`      | `(addresses=*) (verbose=false) (count=MAX) (start=-count)` | Returns info about wallet addresses, filterable by `addresses`.                      |

---

### Working with Non-Wallet Addresses

| Command           | Parameters                 | Description                                                                                |
| ----------------- | -------------------------- | ------------------------------------------------------------------------------------------ |
| `createkeypairs`  | `(count=1)`                | Generates public/private key pairs not stored in wallet, for external management.          |
| `createmultisig`  | `nrequired ["key", ...]`   | Creates P2SH multisig address without adding to wallet. Returns address and redeem script. |
| `validateaddress` | `address\|privkey\|pubkey` | Returns info about an address, private key, or public key, including wallet ownership.     |

---

### Permissions Management

[More Info and Tutorial](#permissions-management)
| Command | Parameters | Description |
|----------------------|-------------------------------------|---------------------------------------------------------------------------------------------------------|
| `grant` | `addresses permissions (native-amount=0) (start-block) (end-block) (comment) (comment-to)` | Grants global or per-entity permissions to addresses. Returns txid. |
| `grantfrom` | `from-address to-addresses permissions ...` | Like `grant`, but specifies `from-address`. |
| `grantwithdata` | `addresses permissions metadata ...` | Like `grant`, adds metadata output. |
| `grantwithdatafrom` | `from-address to-addresses permissions metadata ...` | Combines `grantfrom` and `grantwithdata`. |
| `listpermissions` | `(permissions=*) (addresses=*) (verbose=false)` | Lists explicitly granted permissions. Verbose mode includes admins and pending changes. |
| `revoke` | `addresses permissions (native-amount=0) (comment) (comment-to)` | Revokes permissions from addresses. Returns txid. |
| `revokefrom` | `from-address to-addresses permissions ...` | Like `revoke`, but specifies `from-address`. |
| `verifypermission` | `address permission` | Checks if an address has a specific permission (including implicit ones). Returns boolean. |

---

### Asset Management

[More Info and Tutorial](#asset-management)
| Command | Parameters | Description |
|----------------------|-------------------------------------|---------------------------------------------------------------------------------------------------------|
| `getassetinfo` | `asset (verbose=false)` | Returns info about an asset. Verbose mode includes issuance details. |
| `gettokeninfo` | `asset token (verbose=false)` | Returns info about a token of a non-fungible asset (MultiChain 2.2.1+). |
| `issue` | `address name\|params qty (units=1) (native-amount=min-per-output) (custom-fields)` | Issues a new asset to `address`. Returns txid. |
| `issuefrom` | `from-address to-address name\|params ...` | Like `issue`, but specifies `from-address`. |
| `issuemore` | `address asset qty (native-amount=min-per-output) (custom-fields)` | Issues more units of an open fungible asset. Returns txid. |
| `issuemorefrom` | `from-address to-address asset qty ...` | Like `issuemore`, but specifies `from-address`. |
| `issuetoken` | `address asset token qty (native-amount=min-per-output) (token-details)` | Issues tokens for a non-fungible asset. Returns txid. |
| `issuetokenfrom` | `from-address to-address asset token qty ...` | Like `issuetoken`, but specifies `from-address`. |
| `listassetissues` | `asset (verbose=false) (count=MAX) (start=-count)` | Lists issuance events for an asset. Verbose mode includes details. |
| `listassets` | `(assets=*) (verbose=false) (count=MAX) (start=-count)` | Lists assets on the blockchain. Verbose mode includes issuance details. |
| `update` | `asset params` | Updates asset status (open/closed). Requires admin permissions. Returns txid. |
| `updatefrom` | `from-address asset params` | Like `update`, but specifies `from-address`. |

---

### Querying Wallet Balances and Transactions

| Command                   | Parameters                                                                            | Description                                                                       |
| ------------------------- | ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `getaddressbalances`      | `address (minconf=1) (includeLocked=false)`                                           | Lists asset balances for an address.                                              |
| `getaddresstransaction`   | `address txid (verbose=false)`                                                        | Details a transaction affecting an address. Verbose mode includes inputs/outputs. |
| `getmultibalances`        | `(addresses=*) (assets=*) (minconf=1) (includeWatchOnly=false) (includeLocked=false)` | Lists balances for multiple addresses and assets.                                 |
| `gettokenbalances`        | `(addresses=*) (assets=*) ...`                                                        | Like `getmultibalances`, but for non-fungible tokens only.                        |
| `gettotalbalances`        | `(minconf=1) (includeWatchOnly=false) (includeLocked=false)`                          | Lists total wallet asset balances.                                                |
| `getwallettransaction`    | `txid (includeWatchOnly=false) (verbose=false)`                                       | Details a wallet transaction. Verbose mode includes inputs/outputs.               |
| `listaddresstransactions` | `address (count=10) (skip=0) (verbose=false)`                                         | Lists recent transactions for an address. Verbose mode includes details.          |
| `listwallettransactions`  | `(count=10) (skip=0) (includeWatchOnly=false) (verbose=false)`                        | Lists recent wallet transactions. Verbose mode includes details.                  |

---

### Sending One-Way Payments

| Command            | Parameters                                                                | Description                                     |
| ------------------ | ------------------------------------------------------------------------- | ----------------------------------------------- |
| `send`             | `address amounts (comment) (comment-to)`                                  | Sends payment to an address. Returns txid.      |
| `sendasset`        | `address asset qty (native-amount=min-per-output) (comment) (comment-to)` | Sends an asset to an address. Returns txid.     |
| `sendassetfrom`    | `from-address to-address asset qty ...`                                   | Like `sendasset`, but specifies `from-address`. |
| `sendfrom`         | `from-address to-address amounts ...`                                     | Like `send`, but specifies `from-address`.      |
| `sendwithdata`     | `address amounts metadata`                                                | Like `send`, adds metadata output.              |
| `sendwithdatafrom` | `from-address to-address amounts metadata`                                | Combines `sendfrom` and `sendwithdata`.         |

---

### Atomic Exchange Transactions

[Tutorial](#atomic-exchange-transactions)
| Command | Parameters | Description |
|----------------------|-------------------------------------|---------------------------------------------------------------------------------------------------------|
| `appendrawexchange` | `tx-hex txid vout amounts` | Adds offer to an exchange transaction. Returns updated hex and completion status. |
| `completerawexchange` | `tx-hex txid vout amounts (metadata)` | Finalizes an exchange transaction. Returns hex for broadcasting. |
| `createrawexchange` | `txid vout amounts` | Creates a new exchange transaction. Returns partial hex. |
| `decoderawexchange` | `tx-hex (verbose=false)` | Decodes an exchange transaction. Verbose mode lists all stages. |
| `disablerawtransaction` | `tx-hex` | Disables an exchange by spending an input. Returns txid. |
| `preparelockunspent` | `amounts (lock=true)` | Prepares a locked unspent output for exchanges. Returns txid and vout. |
| `preparelockunspentfrom` | `from-address amounts (lock=true)` | Like `preparelockunspent`, but specifies `from-address`. |

---

### Stream Management

[More Info](#stream-management)
| Command | Parameters | Description |
|----------------------|-------------------------------------|---------------------------------------------------------------------------------------------------------|
| `create` | `type=stream name restrictions (custom-fields)` | Creates a new stream. Returns txid. |
| `createfrom` | `from-address type=stream name open\|params (custom-fields)` | Like `create`, but specifies `from-address`. |
| `getstreaminfo` | `stream (verbose=false)` | Returns stream info. Verbose mode includes creators. |
| `liststreams` | `(streams=*) (verbose=false) (count=MAX) (start=-count)` | Lists streams. Verbose mode includes extra details. |

#### Publishing Stream Items

| Command            | Parameters                                  | Description                                                |
| ------------------ | ------------------------------------------- | ---------------------------------------------------------- |
| `publish`          | `stream key(s) data (options)`              | Publishes a stream item. Returns txid.                     |
| `publishfrom`      | `from-address stream key(s) data (options)` | Like `publish`, but specifies `from-address`.              |
| `publishmulti`     | `stream items (options)`                    | Publishes multiple items in one transaction. Returns txid. |
| `publishmultifrom` | `from-address stream items (options)`       | Like `publishmulti`, but specifies `from-address`.         |

---

### Managing Stream and Asset Subscriptions

| Command         | Parameters                                       | Description                                                      |
| --------------- | ------------------------------------------------ | ---------------------------------------------------------------- |
| `subscribe`     | `asset(s)\|stream(s) (rescan=true) (parameters)` | Tracks assets/streams. `rescan` reindexes history. Returns null. |
| `trimsubscribe` | `stream(s) parameters`                           | **Enterprise**: Stops building certain stream indexes.           |
| `unsubscribe`   | `asset(s)\|stream(s) (purge=false)`              | Stops tracking assets/streams. `purge` removes off-chain data.   |

---

### Querying Subscribed Assets

| Command                 | Parameters                                                               | Description                                                    |
| ----------------------- | ------------------------------------------------------------------------ | -------------------------------------------------------------- |
| `getassettransaction`   | `asset txid (verbose=false)`                                             | Retrieves an asset transaction. Verbose mode includes details. |
| `listassettransactions` | `asset (verbose=false) (count=10) (start=-count) (local-ordering=false)` | Lists asset transactions.                                      |

---

### Querying Subscribed Streams

| Command                     | Parameters                                                                          | Description                                                         |
| --------------------------- | ----------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| `getstreamitem`             | `stream txid (verbose=false)`                                                       | Retrieves a stream item. Verbose mode includes transaction details. |
| `getstreamkeysummary`       | `stream key mode`                                                                   | Summarizes stream items by key with JSON merging options.           |
| `getstreampublishersummary` | `stream address mode`                                                               | Summarizes stream items by publisher with JSON merging options.     |
| `gettxoutdata`              | `txid vout (count-bytes=INT_MAX) (start-byte=0)`                                    | Retrieves transaction output data in hex.                           |
| `liststreamblockitems`      | `stream blocks (verbose=false) (count=MAX) (start=-count)`                          | Lists stream items in specified blocks.                             |
| `liststreamkeyitems`        | `stream key (verbose=false) (count=10) (start=-count) (local-ordering=false)`       | Lists items by key.                                                 |
| `liststreamkeys`            | `stream (keys=*) (verbose=false) (count=MAX) (start=-count) (local-ordering=false)` | Lists stream keys.                                                  |
| `liststreamitems`           | `stream (verbose=false) (count=10) (start=-count) (local-ordering=false)`           | Lists stream items.                                                 |
| `liststreampublisheritems`  | `stream address ...`                                                                | Lists items by publisher.                                           |
| `liststreampublishers`      | `stream (addresses=*) ...`                                                          | Lists stream publishers.                                            |
| `liststreamqueryitems`      | `stream query (verbose=false)`                                                      | Lists items matching a query (keys/publishers).                     |
| `liststreamtxitems`         | `stream txid (verbose=false)`                                                       | Lists items in a specific transaction.                              |

---

### Controlling Off-Chain Data (MultiChain Enterprise Only)

| Command               | Parameters     | Description                                     |
| --------------------- | -------------- | ----------------------------------------------- |
| `purgepublisheditems` | `items`        | Purges off-chain items published by this node.  |
| `purgestreamitems`    | `stream items` | Purges retrieved off-chain items from a stream. |
| `retrievestreamitems` | `stream items` | Queues off-chain items for retrieval.           |

---

### Managing Wallet Unspent Outputs

| Command           | Parameters                                                                               | Description                                                  |
| ----------------- | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `combineunspent`  | `(addresses=*) (minconf=1) (maxcombines=100) (mininputs=2) (maxinputs=100) (maxtime=15)` | Combines UTXOs for performance. Returns txids.               |
| `listlockunspent` |                                                                                          | Lists locked UTXOs in wallet.                                |
| `listunspent`     | `(minconf=1) (maxconf=999999) (["address", ...])`                                        | Lists unspent UTXOs in wallet with asset/permission details. |
| `lockunspent`     | `unlock ([{"txid":"id","vout":n},...])`                                                  | Locks/unlocks specified UTXOs.                               |

---

### Working with Raw Transactions

[Tutorial](#working-with-raw-transactions)
| Command | Parameters | Description |
|----------------------|-------------------------------------|---------------------------------------------------------------------------------------------------------|
| `appendrawchange` | `tx-hex address (native-fee)` | Adds change output to a raw transaction. |
| `appendrawdata` | `tx-hex metadata\|object` | Adds metadata output to a raw transaction. |
| `appendrawtransaction` | `tx-hex [{"txid":"id","vout":n},...] ...` | Adds inputs/outputs to an existing raw transaction. |
| `createrawtransaction` | `[{"txid":"id","vout":n},...] {"address":amounts,...} ...` | Creates a new raw transaction. |
| `createrawsendfrom` | `from-address {"to-address":amounts,...} ...` | Creates a raw transaction with auto-selected inputs. |
| `decoderawtransaction` | `tx-hex` | Decodes a raw transaction with asset/permission details. |
| `sendrawtransaction` | `tx-hex` | Broadcasts a raw transaction. Returns txid. |
| `signrawtransaction` | `tx-hex ([{parent-output},...]) (["private-key",...]) (sighashtype=ALL)` | Signs a raw transaction. Returns hex and completion status. |

---

### Peer-to-Peer Connections

| Command            | Parameters                       | Description                                                       |
| ------------------ | -------------------------------- | ----------------------------------------------------------------- |
| `addnode`          | `ip(:port) command`              | Manually manages peer connections (`add`, `remove`, `onetry`).    |
| `getaddednodeinfo` | `verbose (ip(:port))`            | Returns info about manually added peers.                          |
| `getnetworkinfo`   |                                  | Returns network port and IP info.                                 |
| `getpeerinfo`      |                                  | Returns info about connected peers, including handshake details.  |
| `liststorednodes`  | `(includeOldIgnores=false)`      | Lists known peer nodes (MultiChain 2.3+).                         |
| `ping`             |                                  | Pings peers to measure latency/backlog. Results in `getpeerinfo`. |
| `storenode`        | `ip(:port) (command=tryconnect)` | Adds peer to known nodes list (MultiChain 2.3+).                  |

---

### Messaging Signing and Verification

| Command         | Parameters                  | Description                                    |
| --------------- | --------------------------- | ---------------------------------------------- |
| `signmessage`   | `address\|privkey message`  | Returns base64 signature for a message.        |
| `verifymessage` | `address signature message` | Verifies a message signature. Returns boolean. |

---

### Querying the Blockchain

| Command             | Parameters                      | Description                                                   |
| ------------------- | ------------------------------- | ------------------------------------------------------------- |
| `getblock`          | `hash\|height (verbose=1)`      | Returns block info with varying detail levels (0-4).          |
| `getblockchaininfo` |                                 | Returns blockchain info, including best block hash.           |
| `getblockhash`      | `height`                        | Returns block hash at given height.                           |
| `getchaintotals`    |                                 | Counts blockchain entities (enhanced with `explorersupport`). |
| `getlastblockinfo`  | `(skip=0)`                      | Returns info about recent blocks.                             |
| `getmempoolinfo`    |                                 | Returns memory pool info.                                     |
| `getrawmempool`     |                                 | Lists transaction IDs in memory pool.                         |
| `getrawtransaction` | `txid (verbose=false)`          | Returns raw transaction hex or JSON details.                  |
| `gettxout`          | `txid vout (unconfirmed=false)` | Returns unspent output details with asset/permission info.    |
| `listblocks`        | `blocks (verbose=false)`        | Returns info about specified blocks.                          |
| `listminers`        | `(verbose=false)`               | Lists miners with permission and diversity details.           |

---

### Binary Cache

| Command              | Parameters                                                  | Description                                          |
| -------------------- | ----------------------------------------------------------- | ---------------------------------------------------- |
| `createbinarycache`  |                                                             | Creates a new binary cache item. Returns identifier. |
| `appendbinarycache`  | `identifier data-hex`                                       | Appends hex data to a cache item. Returns size.      |
| `deletebinarycache`  | `identifier`                                                | Removes a cache item.                                |
| `txouttobinarycache` | `identifier txid vout (count-bytes=INT_MAX) (start-byte=0)` | Extracts output data to a cache item. Returns size.  |

---

### Advanced Wallet Control

| Command                  | Parameters                         | Description                                                       |
| ------------------------ | ---------------------------------- | ----------------------------------------------------------------- |
| `backupwallet`           | `filename`                         | Backs up wallet to `filename`.                                    |
| `dumpprivkey`            | `address`                          | Returns private key for an address. Use with caution.             |
| `dumpwallet`             | `filename`                         | Dumps all private keys to `filename`. Use with caution.           |
| `encryptwallet`          | `passphrase`                       | Encrypts wallet with `passphrase`. Requires restart.              |
| `getwalletinfo`          |                                    | Returns wallet info (e.g., tx count, encryption status).          |
| `importprivkey`          | `privkey(s) (label) (rescan=true)` | Adds private key(s) to wallet. `rescan` controls blockchain scan. |
| `importwallet`           | `filename (rescan=0)`              | Imports private keys from `filename`. `rescan` controls scope.    |
| `walletlock`             |                                    | Relocks encrypted wallet immediately.                             |
| `walletpassphrase`       | `passphrase timeout`               | Unlocks wallet for `timeout` seconds.                             |
| `walletpassphrasechange` | `old-passphrase new-passphrase`    | Changes wallet passphrase.                                        |

---

### Working with Feeds (MultiChain Enterprise Only)

| Command                | Parameters                                                | Description                                               |
| ---------------------- | --------------------------------------------------------- | --------------------------------------------------------- |
| `addtofeed`            | `feed entities (globals="") (action=rescan) (options)`    | Adds subscriptions to a feed.                             |
| `createfeed`           | `feed (params)`                                           | Creates a feed for real-time replication.                 |
| `datareftobinarycache` | `identifier dataref (count-bytes=INT_MAX) (start-byte=0)` | Extracts feed data to binary cache.                       |
| `deletefeed`           | `feed (force=false)`                                      | Deletes a feed and its directory.                         |
| `getdatarefdata`       | `dataref (count-bytes=INT_MAX) (start-byte=0)`            | Returns feed data in hex.                                 |
| `listfeeds`            | `(feeds=*) (verbose=false)`                               | Lists feeds on the node.                                  |
| `pausefeed`            | `feed (buffer=true)`                                      | Pauses feed writing. Buffers events if `buffer=true`.     |
| `purgefeed`            | `feed file\|days\|*`                                      | Purges old feed files to free space.                      |
| `resumefeed`           | `feed (buffer=true)`                                      | Resumes feed writing, optionally copying buffered events. |
| `updatefeed`           | `feed entities (globals="") (action=none) (options)`      | Updates feed subscriptions and options.                   |

---

### Smart Filters and Upgrades

[More About Filters and Upgrades](#smart-filters-and-upgrades)
| Command | Parameters | Description |
|----------------------|-------------------------------------|---------------------------------------------------------------------------------------------------------|
| `approvefrom` | `from-address entity approve` | Approves/disapproves an upgrade or filter. Returns txid. |
| `create` | `type=streamfilter name options js-code` | Creates a stream filter. Returns txid. |
| `create` | `type=txfilter name options js-code` | Creates a transaction filter. Returns txid. |
| `create` | `type=upgrade name open=false params` | Creates an upgrade. Returns txid. |
| `createfrom` | `from-address type name ...` | Like `create`, but specifies `from-address`. |
| `getfiltercode` | `filter` | Returns filter JavaScript code. |
| `liststreamfilters` | `(filters=*) (verbose=false)` | Lists stream filters. Verbose mode includes creators. |
| `listtxfilters` | `(filters=*) (verbose=false)` | Lists transaction filters with approval status. |
| `listupgrades` | `(upgrades=*)` | Lists upgrades with approval status. |
| `runstreamfilter` | `filter (tx-hex\|txid) (vout)` | Runs an existing stream filter on a transaction. |
| `runtxfilter` | `filter (tx-hex\|txid)` | Runs an existing transaction filter on a transaction. |
| `teststreamfilter` | `options js-code (tx-hex\|txid) (vout)` | Tests a stream filter before creation. |
| `testtxfilter` | `options js-code (tx-hex\|txid)` | Tests a transaction filter before creation. |

---

### Libraries and Variables

| Command                | Parameters                                                 | Description                                                    |
| ---------------------- | ---------------------------------------------------------- | -------------------------------------------------------------- |
| `addlibraryupdate`     | `library updatename js-code`                               | Adds update to a library. Returns txid.                        |
| `addlibraryupdatefrom` | `from-address library updatename js-code`                  | Like `addlibraryupdate`, but specifies `from-address`.         |
| `approvefrom`          | `from-address library approve`                             | Approves/disapproves library update. Returns txid.             |
| `create`               | `type=library name options js-code`                        | Creates a library. Returns txid.                               |
| `create`               | `type=variable name (open=true) (value=null)`              | Creates a variable. Returns txid.                              |
| `createfrom`           | `from-address type name ...`                               | Like `create`, but specifies `from-address`.                   |
| `getlibrarycode`       | `library (updatename)`                                     | Returns library JavaScript code.                               |
| `getvariablehistory`   | `variable (verbose=false) (count=MAX) (start=-count)`      | Lists variable value history.                                  |
| `getvariableinfo`      | `variable (verbose=false)`                                 | Returns variable info. Verbose mode includes last transaction. |
| `getvariablevalue`     | `variable`                                                 | Retrieves latest variable value.                               |
| `listlibraries`        | `(libraries=*) (verbose=false)`                            | Lists libraries. Verbose mode includes updates.                |
| `listvariables`        | `(variables=*) (verbose=false) (count=MAX) (start=-count)` | Lists variables. Verbose mode includes last values.            |
| `setvariablevalue`     | `variable (value=null)`                                    | Sets variable value. Returns txid.                             |
| `setvariablevaluefrom` | `from-address variable (value=null)`                       | Like `setvariablevalue`, but specifies `from-address`.         |
| `testlibrary`          | `(library) (updatename) (js-code)`                         | Manages local library testing without blockchain changes.      |

---

### Advanced Node Control

| Command               | Parameters     | Description                                                                       |
| --------------------- | -------------- | --------------------------------------------------------------------------------- |
| `clearmempool`        |                | Clears memory pool after pausing incoming/mining tasks.                           |
| `getchunkqueueinfo`   |                | Returns off-chain chunk queue stats (count and bytes).                            |
| `getchunkqueuetotals` |                | Returns cumulative off-chain chunk query/request stats.                           |
| `pause`               | `tasks`        | Pauses specified tasks (`mining`, `incoming`, `offchain`).                        |
| `resume`              | `tasks`        | Resumes specified tasks.                                                          |
| `setlastblock`        | `hash\|height` | Rewinds/switches blockchain to specified block after pausing tasks. Returns hash. |

---

### MultiChain Enterprise Licensing

| Command             | Parameters                     | Description                                        |
| ------------------- | ------------------------------ | -------------------------------------------------- |
| `getlicenserequest` |                                | Returns hex string for license generation.         |
| `activatelicense`   | `confirmation-hex`             | Activates a license. Returns txid.                 |
| `listlicenses`      | `(licenses=*) (verbose=false)` | Lists installed licenses.                          |
| `transferlicense`   | `license request-hex`          | Transfers a license to another node. Returns txid. |

---

## Parameter Forms

### Amounts Parameter Forms

Specifies transaction output content:

-   Native currency: `0.01`
-   Single asset: `{"asset1":50}`
-   Single token: `{"asset1":{"token":"token1","qty":5}}`
-   Multiple assets: `{"asset1":50,"asset2":1.5}`
-   Multiple tokens: `{"asset1":[{"token":"token1","qty":5},{"token":"token2","qty":10}]}`
-   Mixed with native: `{"asset1":{"token":"token1","qty":5},"asset2":1.5,"":0.01}`
-   With binary metadata: `{"asset1":{"token":"token1","qty":5},"data":"a1b2c3d4"}`
-   With textual metadata: `{"asset1":50,"data":{"text":"hello"}}`
-   With JSON metadata: `{"":0.01,"asset1":50,"data":{"json":[1,2,3]}}`

Assets can use name, ref, or issuance txid.

### Metadata Parameter Forms

-   Binary hex: `a1b2c3d4`
-   Binary cache: `{"cache":"Ev1HQV1aUCY"}`
-   Textual: `{"text":"hello world"}`
-   JSON: `{"json":{"i":[1,2],"j":"yes"}}`
-   Binary stream item: `{"for":"stream1","keys":["key1","key2"],"data":"a1b2c3d4"}`
-   Cache stream item: `{"for":"stream1","keys":["key1","key2"],"data":{"cache":"Ev1HQV1aUCY"}}`
-   Textual stream item: `{"for":"stream1","keys":["key1","key2"],"data":{"text":"hello"}}`
-   JSON stream item: `{"for":"stream1","keys":["key1"],"data":{"json":{"i":[1,2]}}}`

Stream items can use `"key":"key1"` instead of `"keys"`, and add `"options":"offchain"`.

---

## Incompatible Changes from MultiChain 1.0

MultiChain 2.x is mostly backwards compatible with 1.0.x, but some changes apply:

-   Stream item `key` replaced by `keys` array.
-   Stream `open` field replaced by `restrict.write`.
-   Wallet transaction stream payloads not in top-level `data`.
-   Raw transaction decoding omits empty arrays.
-   Asset issuance requires explicit `"create":"asset"`.
-   Follow-on issuance requires `"update":[asset-identifier]`.

Set `v1apicompatible=1` for 1.0 field compatibility.

---

## A Note About Accounts

MultiChain does not support Bitcoin Core’s “accounts” with the new wallet (`walletdbversion=2`). Use `getnewaddress` and “from” APIs (e.g., `sendassetfrom`) instead for multi-user support.
