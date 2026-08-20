## [3.6.2](https://github.com/kdbsoft/dockercart/compare/v3.6.1...v3.6.2) (2026-08-18)

### Bug Fixes

* **scripts:** reject nested ${VAR} refs in .env database secrets ([5d90ed3](https://github.com/kdbsoft/dockercart/commit/5d90ed30874d76bdc1af78e3876aa8b5c8ff525e))

## [3.6.1](https://github.com/kdbsoft/dockercart/compare/v3.6.0...v3.6.1) (2026-08-18)

### Bug Fixes

* **start:** keep explicitly set DOCKERCART_URL instead of overwriting it ([20d7352](https://github.com/kdbsoft/dockercart/commit/20d7352060c578622d001d061035190bb49ce99d))

## [3.6.0](https://github.com/kdbsoft/dockercart/compare/v3.5.5...v3.6.0) (2026-08-18)

### Features

* **admin:** add recycle bin for soft-deleted entities ([f329715](https://github.com/kdbsoft/dockercart/commit/f329715d56a97fac28c834814215cd163d81cbc9))
* **admin:** cache update check via scheduled worker ([23ec1f6](https://github.com/kdbsoft/dockercart/commit/23ec1f60372470fc2c97e2ea8b18c6d5ecd492de))
* **admin:** preserve extension type filter on settings back navigation ([25b5cce](https://github.com/kdbsoft/dockercart/commit/25b5cceda1443ed2a73b37db3bff4e6da20a2e35))
* **admin:** redesign dockercart checkout settings with sidebar layout ([ca2b3cf](https://github.com/kdbsoft/dockercart/commit/ca2b3cf13244082659bdb0458403de5ffb6358c9))
* **admin:** redesign sitemap feed settings with stacked subsections ([640cbf3](https://github.com/kdbsoft/dockercart/commit/640cbf3a5145b1b4bf911abf3905c37612dd9518))

### Bug Fixes

* **cart:** include csrf token in ajax cart and quick-view requests ([c7ee885](https://github.com/kdbsoft/dockercart/commit/c7ee885545537e0122a1c430c66e88e51d1b7475))

## [3.5.5](https://github.com/kdbsoft/dockercart/compare/v3.5.4...v3.5.5) (2026-08-17)

### Bug Fixes

* **mysql:** drop unused source_table from manticore reindex migration ([ccac689](https://github.com/kdbsoft/dockercart/commit/ccac689415b9617de70a1b0f31b8702400e205cb))

## [3.5.4](https://github.com/kdbsoft/dockercart/compare/v3.5.3...v3.5.4) (2026-08-17)

### Bug Fixes

* **migrations:** correct user group permission array handling ([998c6e9](https://github.com/kdbsoft/dockercart/commit/998c6e9f6152e5a51d9e5d7407ada4efa80d6a3b))
* **migrations:** repair corrupted user group permission arrays ([5282b49](https://github.com/kdbsoft/dockercart/commit/5282b49c83936f7c004fb9807c72b6287c521cc9))

## [3.5.3](https://github.com/kdbsoft/dockercart/compare/v3.5.2...v3.5.3) (2026-08-17)

### Bug Fixes

* **db:** ensure oc_order_document exists in invoice migrations ([5bc2525](https://github.com/kdbsoft/dockercart/commit/5bc2525b36165e760054306bb85108b736278d6c))

## [3.5.2](https://github.com/kdbsoft/dockercart/compare/v3.5.1...v3.5.2) (2026-08-17)

### Bug Fixes

* **migration:** create abandoned cart table if missing before alters ([34088fd](https://github.com/kdbsoft/dockercart/commit/34088fda7bffa335b870a03e997f6820de48dd9c))

## [3.5.1](https://github.com/kdbsoft/dockercart/compare/v3.5.0...v3.5.1) (2026-08-17)

### Bug Fixes

* **update:** close flock fd in compose/docker children to avoid podman lock leak ([d5b4822](https://github.com/kdbsoft/dockercart/commit/d5b48221e500cf850eeaccf2292375805a143b67))

## [3.5.0](https://github.com/kdbsoft/dockercart/compare/v3.4.2...v3.5.0) (2026-08-17)

### Features

* **security:** add CSRF checks to catalog actions and harden uploads ([930968e](https://github.com/kdbsoft/dockercart/commit/930968ea19fe07fff0a37fbea7c2ffa811df6f8c))
* **security:** auto-generate healthcheck token when left empty ([7082c80](https://github.com/kdbsoft/dockercart/commit/7082c807babf3b176a0fa2bfe747b7839d8b63d2))
* **security:** bootstrap admin password from ADMIN_PASSWORD on boot ([f532f1c](https://github.com/kdbsoft/dockercart/commit/f532f1c6dce8ec708c8e2bf7d47024eb28f4acb0))

### Bug Fixes

* **security:** deny web access to cli/mock scripts and drop svg uploads ([05e572b](https://github.com/kdbsoft/dockercart/commit/05e572bf7007594fe257a7475c0f8b6c6ec42e70))
* **security:** rotate session id on login and harden cookie flags ([9253196](https://github.com/kdbsoft/dockercart/commit/92531968a6cb9bfd41f7ce17692996262b89a1e0))

## [3.4.2](https://github.com/kdbsoft/dockercart/compare/v3.4.1...v3.4.2) (2026-08-17)

### Bug Fixes

* **make:** re-run setup wizard with seed prompt after make clean ([c98bcfe](https://github.com/kdbsoft/dockercart/commit/c98bcfebfe1e88ecfa00da0c19ef9cc14fce6fed))

## [3.4.1](https://github.com/kdbsoft/dockercart/compare/v3.4.0...v3.4.1) (2026-08-16)

### Bug Fixes

* **scripts:** sanitize and derive compose project name from directory basename ([cdd8d1f](https://github.com/kdbsoft/dockercart/commit/cdd8d1fa72c88b35d8817d95ccc6e659072d7150))

## [3.4.0](https://github.com/kdbsoft/dockercart/compare/v3.3.0...v3.4.0) (2026-08-16)

### Features

* **admin:** add empty states to list pages ([ca09478](https://github.com/kdbsoft/dockercart/commit/ca09478c541066670c69eab48e394e005d54b126))
* **theme:** redesign maintenance page with tailwind utilities ([03a6f82](https://github.com/kdbsoft/dockercart/commit/03a6f82a3a8a1175e80846403c4eb5548429edd7))

### Bug Fixes

* **dashboard:** detect configured store via seeded email ([b93e2bc](https://github.com/kdbsoft/dockercart/commit/b93e2bc978a06365a56ea4af4c4bd8cf0de3acaa))
* **docker:** keep .env flat and harden mariadb healthcheck ([4775595](https://github.com/kdbsoft/dockercart/commit/4775595256fc46c8bb5db0e4ecd7c0b29ed9c472))
* **review:** shard media into per-1000 subdirectories ([949ca33](https://github.com/kdbsoft/dockercart/commit/949ca33cb5bf69de52903285f7751671020157f4))
* **scheduler:** treat SIGWINCH as graceful shutdown signal ([5f9c354](https://github.com/kdbsoft/dockercart/commit/5f9c3546370c19ecad7f6a6b82bad09e90c4b36d))

## [3.3.0](https://github.com/kdbsoft/dockercart/compare/v3.2.0...v3.3.0) (2026-08-15)

### Features

* **catalog:** redesign maintenance page with status and contact info ([14411d3](https://github.com/kdbsoft/dockercart/commit/14411d312f9ebe36b11f41f735696dbf18ef5d77))

### Bug Fixes

* **docker:** chown webroot to root:staff for rootless podman ([7580c4e](https://github.com/kdbsoft/dockercart/commit/7580c4edd981be5ffb46821207f93fbc2b78988b))

## [3.2.0](https://github.com/kdbsoft/dockercart/compare/v3.1.2...v3.2.0) (2026-08-15)

### Features

* **tool/update:** add db backup, atomic writes, warnings and reconnect handling to gui updater ([7b7eff8](https://github.com/kdbsoft/dockercart/commit/7b7eff853bb054bdfea58e79f0bd08183670df4a))

### Bug Fixes

* apply migrations from apache entrypoint after base schema ([ffa7e83](https://github.com/kdbsoft/dockercart/commit/ffa7e83600389d52e1b9a5588fb8febb331982db))
* derive store url from domain and listen port for consistent links ([9bd88a6](https://github.com/kdbsoft/dockercart/commit/9bd88a65fb238aedc3821ac403866a8a05345902))

## [3.1.2](https://github.com/kdbsoft/dockercart/compare/v3.1.1...v3.1.2) (2026-08-15)

### Bug Fixes

* **docker:** make VERSION bind mount writable for GUI update worker ([ae4eb1b](https://github.com/kdbsoft/dockercart/commit/ae4eb1b193f968c95ce5c5de183780f29b444e70))
* **entrypoint:** ensure writable storage subdirs exist and re-own after OCMOD refresh ([3f956f6](https://github.com/kdbsoft/dockercart/commit/3f956f6ef095b8835f5a9e60ff5160cb4b5cd451))

## [3.1.1](https://github.com/kdbsoft/dockercart/compare/v3.1.0...v3.1.1) (2026-08-15)

### Bug Fixes

* **admin:** only show update bell when an update is available ([4b41ed5](https://github.com/kdbsoft/dockercart/commit/4b41ed58a8060034d55a45880ba3c7244049f565))

## [3.1.0](https://github.com/kdbsoft/dockercart/compare/v3.0.2...v3.1.0) (2026-08-15)

### Features

* **docker:** namespace containers and network by COMPOSE_PROJECT_NAME ([e18f08f](https://github.com/kdbsoft/dockercart/commit/e18f08fc76d5979d0bc9361b02aa8cd53772cc82))

## [3.0.2](https://github.com/kdbsoft/dockercart/compare/v3.0.1...v3.0.2) (2026-08-15)

### Bug Fixes

* **scripts:** move shellcheck disable to the line it applies to ([249c30c](https://github.com/kdbsoft/dockercart/commit/249c30c001033261e45251dbf1be789512d55464))

## [3.0.1](https://github.com/kdbsoft/dockercart/compare/v3.0.0...v3.0.1) (2026-08-15)

### Bug Fixes

* **infra:** harden letsencrypt cert handling in start.sh ([35231f0](https://github.com/kdbsoft/dockercart/commit/35231f08ef8793acb5c8ee9d3a882558c79e97b7))
* **infra:** quote CDPATH and simplify compose file selection ([fb014c2](https://github.com/kdbsoft/dockercart/commit/fb014c2a60e8d08ea8ab03fead41634840385bac))

## [3.0.0](https://github.com/kdbsoft/dockercart/compare/v2.5.0...v3.0.0) (2026-08-15)

### ⚠ BREAKING CHANGES

* update to v3.0

### Features

* **abandoned-cart:** add abandoned cart recovery with reminder waves ([147ffc6](https://github.com/kdbsoft/dockercart/commit/147ffc6da389fdad69d737ba7bf2a386980767fc))
* **account:** show gift badge on order product lines ([9842bf8](https://github.com/kdbsoft/dockercart/commit/9842bf852be76f5e8c7384bb97466e44465b094a))
* **account:** show product thumbnails in order pages ([0a7d3a3](https://github.com/kdbsoft/dockercart/commit/0a7d3a3f6914c8db5ab6e2b932f1ac2ccee92cfc))
* **account:** show returns on customer order list and detail ([259880b](https://github.com/kdbsoft/dockercart/commit/259880b949c2973f1477147b8f56c19d061cdba2))
* add attribute sets and option sets ([8bc8647](https://github.com/kdbsoft/dockercart/commit/8bc8647f10bbde20ea519ad144bdbbb06c073ee9))
* add browse-by label and update categories page copy ([f7b7bdd](https://github.com/kdbsoft/dockercart/commit/f7b7bddbff096eebf19813d560557907410a288d))
* add guest register prompt and restyle account menu ([5c47a94](https://github.com/kdbsoft/dockercart/commit/5c47a9464f4fc0396af4426fd0ddb92e93973bee))
* add interactive .env setup wizard on first start ([0963b78](https://github.com/kdbsoft/dockercart/commit/0963b7840a328d28418afb06a95a45a5916ff6a3))
* add interactive start mode menu and stop target ([cea2cf1](https://github.com/kdbsoft/dockercart/commit/cea2cf1fc4b32691cd9420ead60306738e695dc8))
* add Manticore search to admin autocompletes with SQL fallback ([8d42e92](https://github.com/kdbsoft/dockercart/commit/8d42e928879874a141d4b75f43d7b835cb43b108))
* add mock media assignment script ([706527b](https://github.com/kdbsoft/dockercart/commit/706527bf9364773a6453f355e0e15cc5db0f7ee4))
* add mock product variant generator ([4372436](https://github.com/kdbsoft/dockercart/commit/43724369391316c3a0c9d5bf29249a8f534d4bea))
* add quantity stepper to call-for-price request ([ef17704](https://github.com/kdbsoft/dockercart/commit/ef177040b2397179e55c2977d454a1a8e68cef36))
* adjust category banner preview to 2:3 ratio ([5f4578a](https://github.com/kdbsoft/dockercart/commit/5f4578ab62394aee6c23abcb88e26ed4132acb8f))
* **admin:** add GUI system update tool ([a40433e](https://github.com/kdbsoft/dockercart/commit/a40433e08b076a678fcce94cab18a3cead113f63))
* **admin:** add module add/edit page headers ([b4ae4db](https://github.com/kdbsoft/dockercart/commit/b4ae4db58920ff16d5007e7a1b95a5aac8c1080a))
* **admin:** add onboarding checklist to dashboard ([cd01f52](https://github.com/kdbsoft/dockercart/commit/cd01f52ef2430ce1c52e451c10f8bc158c09433a))
* **admin:** add quick product search to list toolbar ([78e2321](https://github.com/kdbsoft/dockercart/commit/78e232166e82bb1951b444a78686a326e1e113a1))
* **admin:** add reusable user_filter component with Shopify-style tabs ([23c325c](https://github.com/kdbsoft/dockercart/commit/23c325c0d8c9a5e271625070c5d16c1f4a192197))
* **admin:** add settings tab labels and image dimension entries ([3afe699](https://github.com/kdbsoft/dockercart/commit/3afe699e1e50210b7911a50db15dad211c8d89a4))
* **admin:** annotate configurable product form and rework rewards table ([81a1719](https://github.com/kdbsoft/dockercart/commit/81a1719b9cccfb038f5aa04b656ed3110370ab32))
* **admin:** choose customer group when creating an order ([067ca6a](https://github.com/kdbsoft/dockercart/commit/067ca6ac4a6444390b665643905b3e7066988b3c))
* **admin:** localize sitemap generation status messages ([58dd034](https://github.com/kdbsoft/dockercart/commit/58dd0348055bef16a1c322e8ededed06c31b7b2b))
* **admin:** masonry extensions grid with multi-instance add ([1d48265](https://github.com/kdbsoft/dockercart/commit/1d48265cb465e27aa9c0fd24fdc32898c9e3b165))
* **admin:** show manufacturer and store count in product list ([635bcc7](https://github.com/kdbsoft/dockercart/commit/635bcc76748657028b4835059bc3b4c146d71577))
* **admin:** show reserved stock in order and product views ([721179f](https://github.com/kdbsoft/dockercart/commit/721179f09480a65f0cd800499ccaf0c3ed5370e2))
* **admin:** validate default variant for configurable products ([7e9c525](https://github.com/kdbsoft/dockercart/commit/7e9c525c1cb26e0e125231f70faf67f3619a1174))
* **analytics:** overhaul analytics report with KPI cards, charts, and masonry layout ([3c52f5a](https://github.com/kdbsoft/dockercart/commit/3c52f5ae992e1cead8c1e64a13059e6626baeb03))
* **blog:** show call-for-price buttons on recommended products ([33e888c](https://github.com/kdbsoft/dockercart/commit/33e888c57cb1c5f249656e06db85f6e135ba43bb))
* **blog:** update blog image sizes and form layouts ([bd5dbee](https://github.com/kdbsoft/dockercart/commit/bd5dbeee23f60e1bc34c583778c9252e05d74edd))
* **cart:** honour variant-level customer group price in cart ([15d212c](https://github.com/kdbsoft/dockercart/commit/15d212ca400a7c89d0c7b04748ca8159fadcd456))
* **catalog/reviews:** add modal dialog for writing reviews and refine review UI ([4a5e96e](https://github.com/kdbsoft/dockercart/commit/4a5e96e8bc9b963d4f195c8396ea9000a4f4fc83))
* **catalog:** add "request" mode to call-for-price with one-click order ([00c8a06](https://github.com/kdbsoft/dockercart/commit/00c8a061feee80dbea1a05d4bf623c97e4832625))
* **catalog:** hide disabled variant values and show out-of-stock state ([0091782](https://github.com/kdbsoft/dockercart/commit/0091782b6068116fad2eebdb54ad5529c3d093f0))
* **catalog:** make category listing banner multilingual ([763a139](https://github.com/kdbsoft/dockercart/commit/763a139fe7bcc024dd7ab300a90ac8acf236f918))
* **catalog:** snap variants to nearest in-stock combo ([37c08ad](https://github.com/kdbsoft/dockercart/commit/37c08ad4675d83188730b6c94a5258ccc871d5db))
* **catalog:** sort out-of-stock last and deep-link searched variants ([a069546](https://github.com/kdbsoft/dockercart/commit/a069546273dd683f9248b4d1711374ae49b6e511))
* **category:** select a banner instead of per-language upload ([076f274](https://github.com/kdbsoft/dockercart/commit/076f274e153b6c22c53a55cae5750aa7eeee101f))
* **cfp:** validate phone against country mask on client and server ([a56b3a9](https://github.com/kdbsoft/dockercart/commit/a56b3a9c8715183c027a9119bceed47cf922717d))
* **chart:** add returns data to dashboard chart widget ([90387ee](https://github.com/kdbsoft/dockercart/commit/90387ee95cb4cde8603238fe5d348becc27e6b6f))
* **checkout:** add reward points earn and spend UI ([ab557dc](https://github.com/kdbsoft/dockercart/commit/ab557dc0f1fec8d6a5daf5f95b5d281fafb530c7))
* **checkout:** price bxgy rewards and product gifts at order creation ([659c897](https://github.com/kdbsoft/dockercart/commit/659c897ac631cb4dfe730ed1e6b82fd46ed3c339))
* **checkout:** support variants in BXGY rewards and gifts ([c775c40](https://github.com/kdbsoft/dockercart/commit/c775c4070443e8d668d46deabbd86343a6700788))
* **compare:** allow comparing product variants ([e0c046a](https://github.com/kdbsoft/dockercart/commit/e0c046a6531712ff85ed4e90fda502a74bbce869))
* **controller:** add previewProduct endpoint, line discounts, and autocomplete enhancements ([c6d46bf](https://github.com/kdbsoft/dockercart/commit/c6d46bfb4f9da26483ab365febdce47b3e6c6233))
* **coupon:** add multilingual coupon names with coupon_description table ([770a74a](https://github.com/kdbsoft/dockercart/commit/770a74a5a05af3efa935f6d35675424fac51d07d))
* **customer:** add addresses section with card-based layout ([89138b0](https://github.com/kdbsoft/dockercart/commit/89138b04e8d6f7bab89007c93ef037156a1f5ec2))
* **customer:** add admin customer detail workspace ([c2858b9](https://github.com/kdbsoft/dockercart/commit/c2858b9529bed821eba258a0a4f73435e449f178))
* **customer:** redesign ip list as card-based layout ([38cb262](https://github.com/kdbsoft/dockercart/commit/38cb262640048c41b04f4df123a4e361d6be0694))
* **dashboard:** add category revenue and top products widgets ([948a153](https://github.com/kdbsoft/dockercart/commit/948a1531e02da7df13d1d966016f10faa498484f))
* **dashboard:** add period filter and clickable order cards ([cba4523](https://github.com/kdbsoft/dockercart/commit/cba45231f747b224bcb787795dc67d2d4398ee9a))
* **dashboard:** add view more footer to category revenue and top products widgets ([7ea6e0d](https://github.com/kdbsoft/dockercart/commit/7ea6e0d1886e3d38a56c9e7bec27a7b866182707))
* **dashboard:** link widgets to analytics report with anchor fragments ([559a2f7](https://github.com/kdbsoft/dockercart/commit/559a2f7669686aaabe9b950bf12e0149877689fd))
* **design/seo_url:** group SEO URLs by query+store and add inline keyword editing ([6257b86](https://github.com/kdbsoft/dockercart/commit/6257b86898917c42db322342df3d3e4bc319cb5d))
* **design:** show routes column in layout list ([1569f3d](https://github.com/kdbsoft/dockercart/commit/1569f3dd5b96fde936712cde4d8e4e923ba00486))
* **docker:** add demo/clean seed mode for fresh installs ([c1d970d](https://github.com/kdbsoft/dockercart/commit/c1d970db723c2caa3222d15e0035c3b29d0fd030))
* **filemanager:** mark protected directories with lock icon ([603ca46](https://github.com/kdbsoft/dockercart/commit/603ca46f1d80a6eaab355c9da06711872d90b6c3))
* hide disabled variants from storefront at data source ([3603327](https://github.com/kdbsoft/dockercart/commit/3603327d11df2e2b158cb7aeabfb2ed84a9a615a))
* **invoice:** add invoice language switching and payment order section ([00cd3ca](https://github.com/kdbsoft/dockercart/commit/00cd3cabeb47650b3bf99e218c81439edf97e130))
* **invoice:** add invoice settings and order invoice ([85cda43](https://github.com/kdbsoft/dockercart/commit/85cda436fc0d3129a962c75316c9f00b86a86dd1))
* **invoice:** add seller signature and stamp images ([5d46847](https://github.com/kdbsoft/dockercart/commit/5d46847e77eaa6e545a9b12cfc81491f619aa95b))
* **language:** add new order detail UI strings across locales ([8ae9379](https://github.com/kdbsoft/dockercart/commit/8ae9379337e1fb97c9c696575b33c79b4971e4bb))
* **language:** add text_copy string for copy-to-clipboard ([7e55deb](https://github.com/kdbsoft/dockercart/commit/7e55debc34a6d92a26e2ca08dc8bfd16209c19fd))
* **mail:** add admin order mail notification controller and templates ([27344b1](https://github.com/kdbsoft/dockercart/commit/27344b1d75c5f42d605d8d535c77ac720a861467))
* **manticore:** add order_number field to orders index ([bed43b0](https://github.com/kdbsoft/dockercart/commit/bed43b0286479ad82c15e23dc719b808791da918))
* **manufacturer:** add brand page with quality and support sections ([ba1c7d5](https://github.com/kdbsoft/dockercart/commit/ba1c7d5cff380f18ad6833be0d23c8bc98cee4fe))
* **marketplace:** redesign system events page with bulk actions and improved UX ([db24bfe](https://github.com/kdbsoft/dockercart/commit/db24bfe0a2a4f063ae79b187fb8e11ec6727c49c))
* **menu:** restructure order menu items under 'Orders' parent ([1abf89d](https://github.com/kdbsoft/dockercart/commit/1abf89dbc485f39b573258baf74f9108107a1351))
* **migrations:** add database migrations for variants, mail, search, shipments, returns ([b9f610f](https://github.com/kdbsoft/dockercart/commit/b9f610fb90259f074911ad5abd738afc11ab3b9d))
* **model:** extract calculateProductPricing and add stock/configurable support ([802a4aa](https://github.com/kdbsoft/dockercart/commit/802a4aaeed823ac4f1b9266778797c35d02cbb70))
* **option:** add show option price toggle ([18c7611](https://github.com/kdbsoft/dockercart/commit/18c76114b396c369881549fb654ee89f45f707ae))
* **order-claim:** add exactly-once guard against duplicate orders ([602d6d1](https://github.com/kdbsoft/dockercart/commit/602d6d151c3b614c9a9c3871dae4a0d33e43e698))
* **order-detail:** attach buyer and refine reward and commission actions ([72a47e5](https://github.com/kdbsoft/dockercart/commit/72a47e585c4ef98f9abbcadd6a06d82f56ef0016))
* **order-documents:** add invoice generation and redesign ([52d31c6](https://github.com/kdbsoft/dockercart/commit/52d31c67061c365792889ea80fccaec4a9a95b23))
* **order-flow:** add multilingual language strings ([cd7b223](https://github.com/kdbsoft/dockercart/commit/cd7b2237f608cc35f135779581973fb0fdb386bc))
* **order-flow:** add order flow configurator page ([0b9aa0e](https://github.com/kdbsoft/dockercart/commit/0b9aa0e9686e5f8aec54d3305017a544fd554606))
* **order-flow:** add order flow library and database migration ([cb6c3c5](https://github.com/kdbsoft/dockercart/commit/cb6c3c52ca55eef336a9c0bbda103ce9261a0967))
* **order-flow:** add order flow to admin navigation ([a4b2e36](https://github.com/kdbsoft/dockercart/commit/a4b2e369f24ac403fac393c8775e4fa41bf7d648))
* **order-flow:** add order flow UI to order detail page ([8dc87c9](https://github.com/kdbsoft/dockercart/commit/8dc87c9bc6372f0cdef204fdfd11496b5eec6f9d))
* **order-flow:** enforce order flow on order status changes ([cf0b4d6](https://github.com/kdbsoft/dockercart/commit/cf0b4d6d08eea7154cddaa623f111f33bc91a8bf))
* **order:** add configurable product variant support to orders ([71e340b](https://github.com/kdbsoft/dockercart/commit/71e340b414c85b7e4844ba553e1202619799a5cc))
* **order:** add order status flow stepper ([da7be34](https://github.com/kdbsoft/dockercart/commit/da7be344de391319ef85b23979eeda472ee56930))
* **order:** add OrderLocalizer infrastructure and i18n order history ([27eec13](https://github.com/kdbsoft/dockercart/commit/27eec133268e49a677c25f5466748032ac371ca5))
* **order:** add shipments panel and enhance order management UI ([2997d19](https://github.com/kdbsoft/dockercart/commit/2997d19eeec8f5d92b2942e83792c4ae820ef398))
* **order:** add UTM traffic attribution to orders ([0a30e2d](https://github.com/kdbsoft/dockercart/commit/0a30e2d21046e5865f720a605e8e0477f566e529))
* **order:** apply catalog pricing, gifts and manual overrides when editing ([863924b](https://github.com/kdbsoft/dockercart/commit/863924b944f76e6a2715191d81bc604a9b727f0f))
* **order:** integrate multilingual display in admin order management ([10c008d](https://github.com/kdbsoft/dockercart/commit/10c008dc1888ce16041be47ea3988d15f068c8ed))
* **order:** integrate OrderLocalizer into catalog and dashboard ([22e2acd](https://github.com/kdbsoft/dockercart/commit/22e2acdb81d3d074baaf937159116587841f3bbe))
* pre-hide unavailable axis values to prevent render flash ([c875062](https://github.com/kdbsoft/dockercart/commit/c8750624e81655ff712c04df870f1b116aa72275))
* **product:** add 360° product image viewer ([936fcd2](https://github.com/kdbsoft/dockercart/commit/936fcd20be4cd58411345db3c56116886275ba2d))
* **product:** add bundle carousel with horizontal scroll ([cf0cedc](https://github.com/kdbsoft/dockercart/commit/cf0cedc243b843e51a41c0406ad393b009c3ab6e))
* **product:** add bundle numbering with index ([5232ed6](https://github.com/kdbsoft/dockercart/commit/5232ed66788622e70f89b4a558ee49e6990f73a6))
* **product:** add fbt picker to admin product form ([0047429](https://github.com/kdbsoft/dockercart/commit/00474297ad7a111657d1da162f96d46136834f28))
* **product:** add frequently-bought-together database schema and models ([548b7a9](https://github.com/kdbsoft/dockercart/commit/548b7a91d9110a9f1f62222704180eefd06f9943))
* **product:** add jan and isbn fields to product variants ([7b9f76a](https://github.com/kdbsoft/dockercart/commit/7b9f76a017280d53d44d2ebd2cc1b961c366e0f4))
* **product:** add no-reviews state and variant price preservation ([c4fa079](https://github.com/kdbsoft/dockercart/commit/c4fa0799e023b4a71281c575aef0e3cfba202900))
* **product:** add sale timer countdown for active special prices ([37f1095](https://github.com/kdbsoft/dockercart/commit/37f10953e37c39660772b25cd8b495a17719bb90))
* **product:** add shop-by and listing labels to product pages ([e9a50a5](https://github.com/kdbsoft/dockercart/commit/e9a50a55f20ff2ed3e7016d64b38d5671c9a99fd))
* **product:** add similar products and discontinued product features ([d6ec29a](https://github.com/kdbsoft/dockercart/commit/d6ec29afb09515ccf09e904b4964d443e82b9da6))
* **product:** add variant quantity discounts to storefront and cart ([415c5b2](https://github.com/kdbsoft/dockercart/commit/415c5b2c76dedb779de57ef33fcc1564a9dea0ae))
* **product:** add vote endpoint, upgrade section, and viewed page redesign ([3ddf4b1](https://github.com/kdbsoft/dockercart/commit/3ddf4b1376a99f84a00ab1c86af4ba9d632b1822))
* **product:** increase stock badge font size for better readability ([fc6ac7d](https://github.com/kdbsoft/dockercart/commit/fc6ac7d6fab1e91aa457e49c2d3063d884baebc9))
* **product:** prefer variant model over sku for display ([f76877f](https://github.com/kdbsoft/dockercart/commit/f76877f89e45137037bd543314c609801f030c30))
* **product:** redesign product page with stock badge and brand block ([bac0671](https://github.com/kdbsoft/dockercart/commit/bac067139564ffff354e1b96ef7cf27e7b545f69))
* **product:** show fbt carousel on product page ([d108752](https://github.com/kdbsoft/dockercart/commit/d10875271a9fef73a84ad192fb80a8dca32e7c5b))
* **product:** show option price on product page ([5953614](https://github.com/kdbsoft/dockercart/commit/5953614c584b751a4ac089ba0678d5c7f9462e29))
* **product:** update listing templates with shop-by and all-labels ([1723be8](https://github.com/kdbsoft/dockercart/commit/1723be81a3266d72242a349e9c40cb24bb66bff7))
* **product:** variant-aware wishlist/compare state and richer product cards ([7b9cfa6](https://github.com/kdbsoft/dockercart/commit/7b9cfa6ea83a7a0aa4912cc8795c55b15c7748d8))
* redesign customer records and add summary cards ([96cf6ce](https://github.com/kdbsoft/dockercart/commit/96cf6cea161d8605e175c4f661def85d51c73ab9))
* redesign listing toolbar with grouped controls ([6986b8f](https://github.com/kdbsoft/dockercart/commit/6986b8f6957d727d34d6cccb80217ab6089d11ea))
* **report:** add anchor ids to analytics sections for deep linking ([80396bb](https://github.com/kdbsoft/dockercart/commit/80396bb677f35348cb96b874fdfe99e260fd009e))
* **return:** add multi-item return support with refund and exchange ([fff5fce](https://github.com/kdbsoft/dockercart/commit/fff5fced8393c05c3626d46cda0519b4a797ce34))
* **review:** add admin review detail expansion and pending reviews menu ([23f44a8](https://github.com/kdbsoft/dockercart/commit/23f44a8a7cc495e409e896e36ec76ad270eb68cf))
* **review:** add extended review system with criteria, media, and rating cache ([cdcbc9d](https://github.com/kdbsoft/dockercart/commit/cdcbc9d97836a2dad515ec6b505c73e99569fdbf))
* **review:** add language-aware review count labels and author initials ([2a89332](https://github.com/kdbsoft/dockercart/commit/2a89332badf171ca847936a95b745aee7c58f62c))
* **review:** add review settings and criteria group management ([55d2b5f](https://github.com/kdbsoft/dockercart/commit/55d2b5fb7e7ca603337b7a5a7d238633f5b7b8a2))
* **review:** add review settings link and improve review messages ([aaf78fa](https://github.com/kdbsoft/dockercart/commit/aaf78faa97750a03547ad2bb43d55690cd8879bb))
* **review:** add review settings link and simplify column menu ([13c92bc](https://github.com/kdbsoft/dockercart/commit/13c92bc5f3173b31d547b2812db5c23a73e39931))
* **review:** add review votes with likes and dislikes ([2175549](https://github.com/kdbsoft/dockercart/commit/21755492e32d630e57f65b38b603683ee4024b63))
* **review:** add reviews page and product review integration ([8424c56](https://github.com/kdbsoft/dockercart/commit/8424c56bdefe54b5e6421b494559dd1dd665d109))
* **review:** drop trailing .0 from rating format ([3a948ac](https://github.com/kdbsoft/dockercart/commit/3a948ac51c5ac7fd08fdc24c4d57f56ba59dec78))
* **review:** overhaul review UI templates ([9bb56d3](https://github.com/kdbsoft/dockercart/commit/9bb56d3ddfb022d5a2b34a6fe2b564f70c978f88))
* **review:** redesign review system with improved layout and date formatting ([cf3acb7](https://github.com/kdbsoft/dockercart/commit/cf3acb7beb7f385ba252023bd5fce2301190112d))
* **review:** replace pagination with show-more loading ([e515d6a](https://github.com/kdbsoft/dockercart/commit/e515d6afe9ac1798bf9a9b8f07ed7da2de015c6b))
* **reviews:** add one-level review replies for admins and customers ([f485aad](https://github.com/kdbsoft/dockercart/commit/f485aad5ed239150b7351be97d1454c1dd23fc6b))
* **reviews:** show write-review cta and login prompt in review block ([3a196a5](https://github.com/kdbsoft/dockercart/commit/3a196a5d2cc447c5b054fdd803d8ea27ff8582c8))
* **review:** update sale timer and special page styling ([56c02fc](https://github.com/kdbsoft/dockercart/commit/56c02fcadd6a69f0ade250584c6ab5fdde701a84))
* **review:** upgrade media gallery with navigable lightbox ([932c518](https://github.com/kdbsoft/dockercart/commit/932c5181b17a5f9457c28568350010f933fc50da))
* **reward:** add auto-award and auto-revoke of reward points ([8bb1581](https://github.com/kdbsoft/dockercart/commit/8bb158170d5432899a9ee84b2ebc988f7df56972))
* **reward:** add operation type to reward ledger ([c31d4e5](https://github.com/kdbsoft/dockercart/commit/c31d4e5fe626dfa3cf0c8114da87bcae2a99d8d1))
* **sale-timer:** add labelled countdown timer with days, hours, minutes, seconds ([8e909df](https://github.com/kdbsoft/dockercart/commit/8e909dfb94f5b3a64b4c76a1da527d76d90ab203))
* **sale:** process refunds and returns from order detail ([af8296f](https://github.com/kdbsoft/dockercart/commit/af8296fc390adc105d742d594d694d862da68041))
* **scheduler:** add daily promo auto-renew worker ([409d3c5](https://github.com/kdbsoft/dockercart/commit/409d3c53e17b154836f3487be137a252503c5e06))
* **scheduler:** add multilingual task names and weekly/monthly presets ([7eb3ade](https://github.com/kdbsoft/dockercart/commit/7eb3adee11e6e5a2c23ece34d3e757feb6dc0a9d))
* search configurable products by variant option-value labels ([957eed3](https://github.com/kdbsoft/dockercart/commit/957eed32e3641f948d0e0bf302674071f1b4d859))
* **search:** add category-scoped search with scope indicator ([e57f1dd](https://github.com/kdbsoft/dockercart/commit/e57f1dd12281eeedd87d146f237ec34ec19593eb))
* **search:** add keyboard layout correction for did-you-mean suggestions ([e48089d](https://github.com/kdbsoft/dockercart/commit/e48089d17688eebd41da56d74ae6d363f6b31a90))
* **search:** add query mapping management with CSV import/export ([7de94eb](https://github.com/kdbsoft/dockercart/commit/7de94eb6f432a9d0c56c192d17e7a387ea9b6dbd))
* **search:** add spell correction (did you mean) ([6d4db7e](https://github.com/kdbsoft/dockercart/commit/6d4db7e9358b56a751df337b7617b9f9d7b179bc))
* **search:** add voice search admin configuration and language ([c42c3e4](https://github.com/kdbsoft/dockercart/commit/c42c3e485652c2b769020934cd4b0f687b76c972))
* **search:** add voice search button to catalog search views ([6b401d1](https://github.com/kdbsoft/dockercart/commit/6b401d152507c7cf90bada04604fce4c8484e7a0))
* **search:** add voice search JavaScript and controller injection ([1fb9ddb](https://github.com/kdbsoft/dockercart/commit/1fb9ddbe92e3a8ea1fad3ef239e322466bc3cf2b))
* **search:** persist manticore index across restarts ([d3e90c1](https://github.com/kdbsoft/dockercart/commit/d3e90c1b439672b3068db7797c82515ce9680c61))
* **search:** preserve category_id in SEO URLs for scoped search ([f7948e0](https://github.com/kdbsoft/dockercart/commit/f7948e06259b7dcdec60287e7b45ed5778d2dace))
* **search:** remove status gate from dockercart search module ([494ee8e](https://github.com/kdbsoft/dockercart/commit/494ee8e4a744bd96db3ae68ee5df41c8c719854c))
* **search:** update search module with variant indexing and min chars ([e3cd5b9](https://github.com/kdbsoft/dockercart/commit/e3cd5b9c0efed2a6d9158bb37ad917ed8c1271bb))
* **security:** harden web configs and add app health checks ([41cc328](https://github.com/kdbsoft/dockercart/commit/41cc328a9e780f4f274e0cb64385d06c70b90b1d))
* **seo-url:** add store filter to seo url form ([5650008](https://github.com/kdbsoft/dockercart/commit/56500088d3629636587b41e2d35c4ad4a83ee489))
* **shipping:** add getQuoteForOrder to dockercart_universal shipping model ([646ba79](https://github.com/kdbsoft/dockercart/commit/646ba7974da7c82b1c7e623aa0ee9f88462cc828))
* **sitemap:** add reviews support to dockercart sitemap feed ([5d4dc56](https://github.com/kdbsoft/dockercart/commit/5d4dc56ab44bcfd8fb6f5bbff294d471b9b4690d))
* **slideshow:** pause autoplay on hover ([367d501](https://github.com/kdbsoft/dockercart/commit/367d50185b070c76c4f83ad2cbba9b494a9f9b10))
* **stock-reservation:** add global reserve settings in admin ([b64d6a8](https://github.com/kdbsoft/dockercart/commit/b64d6a8d7a8bc1643912f075017938aa49f41a05))
* **stock-reservation:** add per-payment-method reserve override ([dfc3fda](https://github.com/kdbsoft/dockercart/commit/dfc3fdac7e9ac5a3826ec288f86bef50bb2ff07b))
* **stock-reservation:** add reservation library, migration, and cleanup script ([02b9244](https://github.com/kdbsoft/dockercart/commit/02b924438ea95e27fe1c6c3fe52806214079f549))
* **stock-reservation:** integrate holds into checkout flow and cart ([d7a20fa](https://github.com/kdbsoft/dockercart/commit/d7a20fad154ff699f173e97468f16b1a3a246d6e))
* **stock-reservation:** release holds when orders are processed or cancelled ([f4c7752](https://github.com/kdbsoft/dockercart/commit/f4c7752a5b3504d4323081cb4f1e125577ab7f5d))
* **storefront:** expose review id in review list data ([3e5b84a](https://github.com/kdbsoft/dockercart/commit/3e5b84a1e0fa65e3f8d1617aec7785a6022f58c6))
* **storefront:** pulse stock status dot ([0245071](https://github.com/kdbsoft/dockercart/commit/0245071ae62e50586f2432a33960d5a56de994ee))
* **storefront:** restyle product buy box and call-for-price ([a5a2332](https://github.com/kdbsoft/dockercart/commit/a5a23325780233cf1fd27b3c815d76ce120772a0))
* **storefront:** restyle quantity discount blocks ([521dc35](https://github.com/kdbsoft/dockercart/commit/521dc35f7a3a6b9b074e4f037a860442dfc6a504))
* **storefront:** rework variant price, sale and stock UI ([ae09ffb](https://github.com/kdbsoft/dockercart/commit/ae09ffb0166d68a81ce14c633e216d3ea7a1eb5b))
* **storefront:** show partial stars for fractional ratings ([0e08faa](https://github.com/kdbsoft/dockercart/commit/0e08faa8a680ef0010c90c42d80a06c9991913a3))
* **storefront:** show rating distribution popover on product cards ([e60a3b1](https://github.com/kdbsoft/dockercart/commit/e60a3b11db4b3afb5d6e46c176ededcc09a4d9c4))
* **system:** make dockercart_theme and dockercart_checkout system extensions ([8a4ab34](https://github.com/kdbsoft/dockercart/commit/8a4ab34ac1db9694a241ce15c0bbcc69309ac3e7))
* **template:** add accent bar headings across storefront templates ([9f8bf87](https://github.com/kdbsoft/dockercart/commit/9f8bf87008500986e25f08ba564e34436d9cb5e8))
* **template:** add product picker with search, stock badges, line discounts, and preview ([f64318f](https://github.com/kdbsoft/dockercart/commit/f64318fdd65d1907452bb54ca029de134b939ebe))
* **ui:** add data-toggle tooltips and copy-to-clipboard buttons ([d52b267](https://github.com/kdbsoft/dockercart/commit/d52b267aca808a7c843cf9fe959b84efc29d1a84))
* **ui:** add drag/swipe support and responsive track to carousel and slideshow ([34f4ccf](https://github.com/kdbsoft/dockercart/commit/34f4ccf382f57f6760917d09b429a51a990ca1c6))
* **ui:** improve error summary and dcx-field validation ([e67dd49](https://github.com/kdbsoft/dockercart/commit/e67dd4943aa7f93a9107037c4108dfaacf4d30dc))
* update to v3.0 ([c5098dc](https://github.com/kdbsoft/dockercart/commit/c5098dc8c94d70d65c28e3a330206bec08370920))
* **update:** reconcile gui-synced files before fast-forward pull ([724dfd3](https://github.com/kdbsoft/dockercart/commit/724dfd3561c7f3aaac16240a1bc89345fa482208))
* **wishlist:** support per-variant wishlist items ([dfac345](https://github.com/kdbsoft/dockercart/commit/dfac3453ca04d8e2b018fae041f831813790ac6a))

### Bug Fixes

* add login icon to review reply prompt ([9d7eec2](https://github.com/kdbsoft/dockercart/commit/9d7eec22058664e4554496bc2f436344c86ac82c))
* **admin:** default review verified flag when not provided ([7ffa8c6](https://github.com/kdbsoft/dockercart/commit/7ffa8c6dcf67dba5e98f8899f60b2950eb0ac8dd))
* **admin:** disable reward buttons when order has no points ([786a860](https://github.com/kdbsoft/dockercart/commit/786a86064da67ace19d76700877c41c9742962e9))
* **admin:** ignore required-field asterisk in validation errors ([ab2bcd8](https://github.com/kdbsoft/dockercart/commit/ab2bcd850dd31ce1feb41dfe6afc80b67bff264d))
* allow header phone row to wrap on small screens ([fb1e128](https://github.com/kdbsoft/dockercart/commit/fb1e128b71eb684949d2b5449f884fe8cd018376))
* apply group discount/markup to configurable variants ([ec352ab](https://github.com/kdbsoft/dockercart/commit/ec352ab7f368956d51c7c9126f9c0173b3856bb3))
* base option price adjustments on sale price for configurable variants ([7674a3a](https://github.com/kdbsoft/dockercart/commit/7674a3aa6e7725271d37cb556553a9623f27d63c))
* **blog:** update blog post hero image dimensions ([a0e084c](https://github.com/kdbsoft/dockercart/commit/a0e084c7651335c1d1f409183db3ed429f53f53a))
* **checkout:** add transaction safety and fraud detection to catalog models ([8c38510](https://github.com/kdbsoft/dockercart/commit/8c38510cf0ebdd4d6cdc344067927739712df7fc))
* **checkout:** allow re-recovering an abandoned cart in one session ([1093cc5](https://github.com/kdbsoft/dockercart/commit/1093cc5ca54b88a90e7601152cb37318e83abdfd))
* **checkout:** release holds when orders are cancelled or deleted ([41bc703](https://github.com/kdbsoft/dockercart/commit/41bc703d71f705ee59429bfff746a4c57d40df59))
* **checkout:** use session id in duplicate-order claim guard ([1d0a10f](https://github.com/kdbsoft/dockercart/commit/1d0a10fedd7b5b85f8e25c1fe0cc788b90eaa3f2))
* correct form grid column offsets and migrate banner link field ([440e4c9](https://github.com/kdbsoft/dockercart/commit/440e4c991dc05fdd2c8bff87cacc107bae2a98a0))
* **currency:** always render space before right currency symbol ([70a49c2](https://github.com/kdbsoft/dockercart/commit/70a49c2948958979b8d4f7b2ccb8f1d8c72b167d))
* **customer:** consume token atomically and upsert login attempts ([bf326a8](https://github.com/kdbsoft/dockercart/commit/bf326a8690ee3ae85dfdfc964e5bcc57631a76c9))
* **db:** remove mysqli error-report exceptions on connect ([de6a0d8](https://github.com/kdbsoft/dockercart/commit/de6a0d8695a73d7471ccf2f481301974e9429007))
* **demo:** apply vat 5 to taxable goods ([5dc6727](https://github.com/kdbsoft/dockercart/commit/5dc6727cf1686385953d0e6e6d2f72f7a5c8ae50))
* **demo:** link vat geo zone to ukraine zones ([79a2726](https://github.com/kdbsoft/dockercart/commit/79a2726507978d116caaeab685489aeb0560e547))
* detect mariadb-dump failure in dump-init ([4e7bf99](https://github.com/kdbsoft/dockercart/commit/4e7bf99e8ab0236ad74534db7c9e2620f9f03be1))
* drive sale timer by active variant special for configurable products ([fd2b7b3](https://github.com/kdbsoft/dockercart/commit/fd2b7b30fd4f0dc5f149bf2ef8b1ead80241a452))
* enforce whole-number ratings from 1 to 5 ([3a8e80e](https://github.com/kdbsoft/dockercart/commit/3a8e80ed3b897aa721347a6b1c6e42012cbd5c12))
* **frontend:** keep wishlist/compare badges and state in sync ([a12ec24](https://github.com/kdbsoft/dockercart/commit/a12ec246379b38fac0d0b4fc3b7940d320549a7e))
* hide price in search dropdown for call-for-price products ([21c567c](https://github.com/kdbsoft/dockercart/commit/21c567cc6fcf3cb4622747b1a6ffee290df85c41))
* **i18n:** shorten coupon apply button label ([96a0da2](https://github.com/kdbsoft/dockercart/commit/96a0da2cb0018621bad8e766e3113f3491971863))
* **i18n:** translate date_added labels to creation date terminology ([e3baa16](https://github.com/kdbsoft/dockercart/commit/e3baa165ffe6f88049a55d8e436bd9370eda9a3d))
* **i18n:** unify weight class terminology in ru/uk admin ([3fe6474](https://github.com/kdbsoft/dockercart/commit/3fe6474756ebf8fb1573bdbe91e472bdb228a158))
* improve review modal scrolling and alert layout ([62fcb01](https://github.com/kdbsoft/dockercart/commit/62fcb01232f2adf93da9717c8949d419b9e4cf06))
* **install:** restore 20% VAT for taxable goods ([ff6a82a](https://github.com/kdbsoft/dockercart/commit/ff6a82a0e5c4c3969701936c5537a7004d5e5d2c))
* **language:** minor language string corrections ([c6b49b0](https://github.com/kdbsoft/dockercart/commit/c6b49b05cc73fb5789e53ea2f9f68943b755a2db))
* localize sale badge and category labels ([4223ecd](https://github.com/kdbsoft/dockercart/commit/4223ecd5939c5da6f96b1c66a891a18062e03cda))
* narrow review rating to whole-number tinyint ([444721f](https://github.com/kdbsoft/dockercart/commit/444721fb89708d587640266ac9d1a4554d77d6b4))
* open cfp request modal before card navigation ([0a8df82](https://github.com/kdbsoft/dockercart/commit/0a8df82ea6bc889b825cbbfcb8cb39376a2a3db5))
* **product:** align fbt admin label wording in ru/uk locales ([261f359](https://github.com/kdbsoft/dockercart/commit/261f3590f5402a61e4ac4cc51d7870eda49b70b6))
* **product:** display formatted price on gift cards ([08657dc](https://github.com/kdbsoft/dockercart/commit/08657dc0b49f8c40863d10b44a12b64a75aa7f26))
* **product:** hide quantity discount tiers not cheaper than special ([e4ea8de](https://github.com/kdbsoft/dockercart/commit/e4ea8deb6fd2fe16518cf33189e9c9ca15740fa3))
* **product:** keep attribute show-more within columns ([d697480](https://github.com/kdbsoft/dockercart/commit/d697480a6a3b09ea328b54e44b82c3a604ad4533))
* **product:** render configurable variant oos state server side ([778db96](https://github.com/kdbsoft/dockercart/commit/778db96389443120e8b98cb73e9f3718948c159f))
* **product:** round rating to one decimal place ([e468c27](https://github.com/kdbsoft/dockercart/commit/e468c272c4d54cebf5f93cd547e808e6d9fe4517))
* **product:** use variant own special as full price base ([0a929f0](https://github.com/kdbsoft/dockercart/commit/0a929f0665696c420e2d14efa6bd1970bd3c77d7))
* refine order detail and flow stage templates ([2a1ce04](https://github.com/kdbsoft/dockercart/commit/2a1ce04a5b7cc1fb15bf9838a5b7c1021c9ac6d4))
* render lucide icons in dynamically loaded checkout content ([0b08e60](https://github.com/kdbsoft/dockercart/commit/0b08e60d2f74c9b575e80ff07df54308bcd73cd8))
* **reservation:** skip unholdable lines instead of failing batch ([3c54db1](https://github.com/kdbsoft/dockercart/commit/3c54db17fdd655364bf0f4362dbd1527d591b2ff))
* **review:** add missing language strings for review setting and reviews page ([27ccc8e](https://github.com/kdbsoft/dockercart/commit/27ccc8edb807efa667e083fe11d6e14a3a98d793))
* **review:** polish admin review status labels and quick edits ([4fd82bd](https://github.com/kdbsoft/dockercart/commit/4fd82bd65879c7cfc7707626eba25049c1deff5b))
* **reviews:** include product_id in review reply url ([accb4c1](https://github.com/kdbsoft/dockercart/commit/accb4c1408ada957388c9a4f42a8689b33120f05))
* **sale:** compare payment sums at currency precision ([216b9bd](https://github.com/kdbsoft/dockercart/commit/216b9bdc3f052a715f64f2a2ed2c4f4ad5d77427))
* **sale:** exclude refunded orders from pending count ([de9daee](https://github.com/kdbsoft/dockercart/commit/de9daeebc829fe2a3834b328a73c3c83b48355ee))
* **sale:** load catalog total models when confirming orders ([ee173ad](https://github.com/kdbsoft/dockercart/commit/ee173ad9087747c7f1d60d9263ef00e76df9fb82))
* **storefront:** align variant pricing and preorder stock UI ([b4dc8bc](https://github.com/kdbsoft/dockercart/commit/b4dc8bc77bc9738f1f6887eefa74b9e6eec5ee88))
* **storefront:** allow clearing a selected optional option ([d0af743](https://github.com/kdbsoft/dockercart/commit/d0af743e53fbb12c0cdd935d028e25408542456f))
* **storefront:** keep mobile menu hidden on desktop ([2a8fa27](https://github.com/kdbsoft/dockercart/commit/2a8fa2721c911d80578305c2425cd1af86927ba7))
* **storefront:** redirect slash-form routes to canonical seo keyword ([e5d4cd3](https://github.com/kdbsoft/dockercart/commit/e5d4cd3e097f03ce322c1039e2aa4853f6a2cc75))
* **storefront:** tint pre-order stock badge dot amber ([c380367](https://github.com/kdbsoft/dockercart/commit/c3803670c9c1b8c837d8d583e061640acf17bbe4))
* **storefront:** use database stock status in product badge ([0cd28bb](https://github.com/kdbsoft/dockercart/commit/0cd28bb05a2d00d6b2ebfeb9c5d3034cf67b6c1d))
* **template:** add store name fallback in footer ([bf604ad](https://github.com/kdbsoft/dockercart/commit/bf604ad60325c7edc6ebed1a67e38233b8ce9543))
* **tooltip:** add data-toggle attribute to option set selector tooltip ([e7ee6af](https://github.com/kdbsoft/dockercart/commit/e7ee6af34d6d5cf1f31016709737bdea7686f095))

### Performance Improvements

* **catalog:** add bulk hydration methods to kill N+1 queries ([7d8c1e6](https://github.com/kdbsoft/dockercart/commit/7d8c1e65a5bd7560c5d3c9266c288156d3411e86))
* **catalog:** consume bulk hydration across listings, cart and checkout ([c2a735d](https://github.com/kdbsoft/dockercart/commit/c2a735dd8d3283b3d66c4a8d182465a8096bb5e9))

## [2.5.0](https://github.com/kdbsoft/dockercart/compare/v2.4.1...v2.5.0) (2026-07-31)

### Features

* **analytics:** add day-of-week labels to analytics report ([01c84b0](https://github.com/kdbsoft/dockercart/commit/01c84b0237f2c4fbba8bfa4270731a9633c76c94))
* **order:** add inline editable panels with payment/shipping ([682e76a](https://github.com/kdbsoft/dockercart/commit/682e76a6bf46b84d237d3729029722984b2388b4))
* **order:** add multiple tracking numbers with modal editor ([96efa01](https://github.com/kdbsoft/dockercart/commit/96efa01fd3f97fa84496747cc14d0d1e7d8bf1ec))
* **order:** add payment tracking with order_payments table ([de5acb3](https://github.com/kdbsoft/dockercart/commit/de5acb35955215c1ecab3a48b85baf34144b4e0f))
* **order:** improve payment reference labeling and add hint ([1a89e9f](https://github.com/kdbsoft/dockercart/commit/1a89e9f0eb46722677cf260a7f85ec613738c0bc))

### Bug Fixes

* **manufacturer:** reposition status field above stores in manufacturer form ([d9d19ce](https://github.com/kdbsoft/dockercart/commit/d9d19ceb6d0f6724ecedbcb923afbbed46b206e4))
* **return:** exclude completed returns from pending count ([71822f4](https://github.com/kdbsoft/dockercart/commit/71822f443f4c0796ac559b7595638ed6ccca2a39))

## [2.4.1](https://github.com/kdbsoft/dockercart/compare/v2.4.0...v2.4.1) (2026-07-30)

### Bug Fixes

* **ci:** correct docker compose health check flag syntax ([1cba1f8](https://github.com/kdbsoft/dockercart/commit/1cba1f87a2bec6f5522900f85cdb1b41a041518f))

## [2.4.0](https://github.com/kdbsoft/dockercart/compare/v2.3.0...v2.4.0) (2026-07-30)

### Features

* **api:** remove old OpenCart API framework ([9cb53e3](https://github.com/kdbsoft/dockercart/commit/9cb53e365e8079b0029c3449c00c1a084c4b5078))
* **dashboard:** enhance recent orders with product info and tracking ([9122b44](https://github.com/kdbsoft/dockercart/commit/9122b445ab889e72da9304325dc3e17addcc6e38))
* **meta:** update meta description dynamically on variant selection ([28b6678](https://github.com/kdbsoft/dockercart/commit/28b667884b92ec1e53bec807eb1b5d54e6659287))
* **migration:** add variant discount and model migrations ([9f0c2d2](https://github.com/kdbsoft/dockercart/commit/9f0c2d2dd1a4760341ae200b1812a9078f708a02))
* **order-detail:** add product card modal, product search, and timeline pagination ([8ec26ba](https://github.com/kdbsoft/dockercart/commit/8ec26ba711b83c1dbaab52c2c69d8c2820040073))
* **order:** add color option type support in order info ([b88ec20](https://github.com/kdbsoft/dockercart/commit/b88ec20cf6bdb92d8fdc58fd5ae7796a46180844))
* **order:** add order detail view with timeline ([37d6466](https://github.com/kdbsoft/dockercart/commit/37d646603499367e099952024a4abe85a7f7391c))
* **order:** improve inline editing with save state and quantity format ([dd60ce9](https://github.com/kdbsoft/dockercart/commit/dd60ce9e85cfe20de8ebfa8ddf14f401bd137d89))
* **order:** sync api cart before saving inline order changes ([89ec34d](https://github.com/kdbsoft/dockercart/commit/89ec34d225afad35d45fbb3462eb10a94f10c80d))
* **product:** add configurable variant stock validation and UI ([ff6ae7d](https://github.com/kdbsoft/dockercart/commit/ff6ae7d55d790b3b28c54ef5aa9d6c51740d5daa))
* **swatch:** render color circle swatches on product cards ([f7da953](https://github.com/kdbsoft/dockercart/commit/f7da953e52ec4623ab379a4f3c76d5955c787324))
* **ui:** add discount tab, model column, max axes limit in product admin ([3e6d7d0](https://github.com/kdbsoft/dockercart/commit/3e6d7d08fcf954a5333dc621be803d32e732f4c2))
* **variant:** add quantity discounts and model field support ([b3da270](https://github.com/kdbsoft/dockercart/commit/b3da270ce278be8017b75bd56b2ff44aa00821cc))
* **variant:** add variant_id and variant_sku to order and cart responses ([c727687](https://github.com/kdbsoft/dockercart/commit/c727687d6a1ffa6311c8e21fca3d0119a4d955e5))
* **variant:** allow configurable products in cart and add variant data to checkout ([aa18a8c](https://github.com/kdbsoft/dockercart/commit/aa18a8caca529455047e771d9a8d2ad97c722215))

### Bug Fixes

* **admin:** add table-bordered class to admin search table ([8993d25](https://github.com/kdbsoft/dockercart/commit/8993d254a18eaa4fb0d106203ae2d6849da2d1be))
* **cart:** remove debug logging from batch add endpoint ([7253d50](https://github.com/kdbsoft/dockercart/commit/7253d5092a9f3abfdec01d3d4d148b6f4a0e639e))
* **cart:** stop clearing shipping/payment methods on add to cart ([8a9a435](https://github.com/kdbsoft/dockercart/commit/8a9a43509d233f8cf40a93f798c02d0a0381fac5))
* **model/sale:** handle null reward value in order product insert ([537993f](https://github.com/kdbsoft/dockercart/commit/537993fa4f4126e8417483d34bdd0274fbffdf55))
* **order-detail:** normalize product description in card modal ([65938fc](https://github.com/kdbsoft/dockercart/commit/65938fc4b005d6b9918e542d28ac6cff556b5015))
* **ru-ua:** correct icon translation and remove trailing blank lines ([1625264](https://github.com/kdbsoft/dockercart/commit/1625264be1ae1a0663da57f40a4dc0f6f2d88105))

## [2.3.0](https://github.com/kdbsoft/dockercart/compare/v2.2.0...v2.3.0) (2026-07-29)

### Features

* add 'All Categories' button to category filter ([54cd4a7](https://github.com/kdbsoft/dockercart/commit/54cd4a77e79ee8e8fc6e70566d96579f8b2f1976))
* add active/inactive translations and update badge ([8a07bf3](https://github.com/kdbsoft/dockercart/commit/8a07bf330328e9ea64520728d15731924b0e6144))
* add background image support for blog posts ([5658d5b](https://github.com/kdbsoft/dockercart/commit/5658d5b1b3f5e01008c09d4a439c209595079942))
* add blog category tree endpoint and refactor CSS ([9b3a5c9](https://github.com/kdbsoft/dockercart/commit/9b3a5c92e838500f826bf0bcffce0ad293e9af6e))
* add Buy X Get Y (BXGY) promotion support ([0d034d9](https://github.com/kdbsoft/dockercart/commit/0d034d9abfe41680add39e3d3d74e7b5320038e0))
* add card titles and refactor templates to DCX UI ([cf8d12c](https://github.com/kdbsoft/dockercart/commit/cf8d12c2f663b053344f3d120c68812e7dcbf9dd))
* add date_added column and update ordering/UI ([72a3a47](https://github.com/kdbsoft/dockercart/commit/72a3a4782a848241ceb76ad0276750cc40d15623))
* add date_added to product promos ([ee723da](https://github.com/kdbsoft/dockercart/commit/ee723da868e763ddab68a86c2abd6cd3feafa2c1))
* add delete button strings to shop features module ([711ea7d](https://github.com/kdbsoft/dockercart/commit/711ea7d16a905666fe3837839ba65b4b9d576fee))
* add full-width option to banner module ([ebe056f](https://github.com/kdbsoft/dockercart/commit/ebe056f0773b3a7dc52179d42e6bb371727b7cd7))
* Add inline editing for manufacturer status ([880f8d8](https://github.com/kdbsoft/dockercart/commit/880f8d8c0bd09243afa3b93f37f413113b41e786))
* add inline editing raw fields and UI updates ([88b8c7d](https://github.com/kdbsoft/dockercart/commit/88b8c7d29eec46e0385c4a9f75e18454a778d2e9))
* add Lucide icons to admin menu sidebar ([18a12d2](https://github.com/kdbsoft/dockercart/commit/18a12d2b6a518f377af86ccb84e57a1bd7c3fef0))
* add new extensions and dashboard widgets ([9f7337e](https://github.com/kdbsoft/dockercart/commit/9f7337ecd4610a68e054dc3d721292bde4975738))
* add settings card and update option form UI ([4f290a2](https://github.com/kdbsoft/dockercart/commit/4f290a255cf64aa65f1ae838fddd71f9fbf6fd55))
* add sidebar cards, i18n, reindex messages ([5aaaf0b](https://github.com/kdbsoft/dockercart/commit/5aaaf0b8fff98521be19b35115573176175d5baf))
* add sort order to product option values ([2048f2c](https://github.com/kdbsoft/dockercart/commit/2048f2c2a33e22a560adddfadaaaad625a098175))
* add status column and inline editing to information ([29435a1](https://github.com/kdbsoft/dockercart/commit/29435a1fadebe22eb5d11342511d7c4b2814f200))
* add status field to download table and UI ([2e199d5](https://github.com/kdbsoft/dockercart/commit/2e199d5e5b5bf188e7fbda6851ae83c6131203fa))
* add status field to manufacturers ([6a6fa2f](https://github.com/kdbsoft/dockercart/commit/6a6fa2f63f9e54b933744dbc6adc073cc4d891fb))
* add status to attributes, groups, options ([fef9778](https://github.com/kdbsoft/dockercart/commit/fef9778494862de1c6bdd5162965a075153f6565))
* add status, links, and about cards to blog module ([f527f45](https://github.com/kdbsoft/dockercart/commit/f527f456daa710f486c08c053331ba9cd29224b8))
* add translations for phone format, category module, and axis lock warning ([9e19273](https://github.com/kdbsoft/dockercart/commit/9e19273c263598256100cc2c86f5fb303f416977))
* Add upsell and accessory products ([c5fef6c](https://github.com/kdbsoft/dockercart/commit/c5fef6c706bee4d95589b935eb823b8cf656aadb))
* add variant hash and FK support for configurable products ([290094a](https://github.com/kdbsoft/dockercart/commit/290094ae6b96400915ba2e0e8019766c3cfc985d))
* add variant special prices ([c01c1b3](https://github.com/kdbsoft/dockercart/commit/c01c1b39a6e0a09883e7c409abe6d056bf72bc09))
* **admin/templates:** add status badges to report forms and common form templates ([59473bd](https://github.com/kdbsoft/dockercart/commit/59473bda4136b6e1c9ad0bc3fb97d26d3cb45ff5)), closes [#XXX](https://github.com/kdbsoft/dockercart/issues/XXX) [#YYY](https://github.com/kdbsoft/dockercart/issues/YYY)
* **admin:** add active/inactive status labels to checkout module ([0a7e033](https://github.com/kdbsoft/dockercart/commit/0a7e0336da70b3cef3f3f25adb4c4569c74753fa))
* **admin:** add collapsible sidebar with flyout navigation ([6ea36f8](https://github.com/kdbsoft/dockercart/commit/6ea36f88b0ad18524e6c2df394a111309f8a9d94))
* **admin:** add pill navigation, status badges, and card list styles ([0c21587](https://github.com/kdbsoft/dockercart/commit/0c2158716cc8ae9c5086ab68b7eaac0f51a43ae4))
* **admin:** add sidebar toggle icon swap on collapse/expand ([f4d744e](https://github.com/kdbsoft/dockercart/commit/f4d744e043e9de1592f818db2e5e726bc0e4a9b1))
* **database:** update init.sql schema and seed data ([8bc56f1](https://github.com/kdbsoft/dockercart/commit/8bc56f134ff812d672b76ff6da8de043cd306a28))
* delete variants on axis removal ([2c7088a](https://github.com/kdbsoft/dockercart/commit/2c7088adcc8ab52de87153765187c1b186b01c60))
* enhance review form UI with sidebar card ([c883059](https://github.com/kdbsoft/dockercart/commit/c883059ada55645aefe13713824222c5fad1d1b9))
* **extension:** blog sub-modules inherit status from main blog module ([1c6db6f](https://github.com/kdbsoft/dockercart/commit/1c6db6fd996526929da76f3365df035acc2a1667))
* **extension:** track enabled module instances for overall extension status ([153c070](https://github.com/kdbsoft/dockercart/commit/153c0702c11a45a23284a091a587116b0400b812))
* **image:** update the no-image placeholder graphic ([216046a](https://github.com/kdbsoft/dockercart/commit/216046a36adc08605292358736804453345eadd5))
* **image:** update the product placeholder graphic ([a659166](https://github.com/kdbsoft/dockercart/commit/a6591661dff6293e7c499795644f42d859e37d6b))
* Improve attribute and attribute group UI ([98b007b](https://github.com/kdbsoft/dockercart/commit/98b007b0e76fb8a152fe3e446f5bb0ee8d9482d2))
* **product:** add variant interaction javascript ([764f78e](https://github.com/kdbsoft/dockercart/commit/764f78e2a5c6605aa4a6923ba57c2f48d3b2d01d))
* **product:** add variant selection and price override from url ([bae62ce](https://github.com/kdbsoft/dockercart/commit/bae62cee142cf93c7a1a5b82975ccb98507cc61e))
* **product:** add variant swatch templates and styles ([3054e09](https://github.com/kdbsoft/dockercart/commit/3054e09805cbc16797c3be6d18bb274194f73b53))
* **product:** add variant swatches and default option data ([9fd4785](https://github.com/kdbsoft/dockercart/commit/9fd478501fa99f3bfc2c57e86d145c11b75b883e))
* **product:** add variant swatches to listing pages ([3c99e8d](https://github.com/kdbsoft/dockercart/commit/3c99e8dcf531813181c90f7beda033d57c39310e))
* remove image from option values ([8a060c9](https://github.com/kdbsoft/dockercart/commit/8a060c9849fd7bbe9c55eb8d85a9a32bf817e766))
* **seo:** add /variant-n suffix support for product variant urls ([e26a2ca](https://github.com/kdbsoft/dockercart/commit/e26a2ca700a6b9b86436977ff889aa1271f51d0a))
* upgrade lucide from 0.577.0 to 1.25.0 ([2a40677](https://github.com/kdbsoft/dockercart/commit/2a40677a7411f1ada4dd98512eb1b2e928c3434b))

### Bug Fixes

* **admin:** adjust form element sizing and alignment ([2ee5b4f](https://github.com/kdbsoft/dockercart/commit/2ee5b4f2a4bc941196c26b0e740f3034b59f3eef))
* **admin:** widen extension store page layout ([c18dd5b](https://github.com/kdbsoft/dockercart/commit/c18dd5b278f7b179706731e50e3f85f812866952))
* **extension:** fallback to code-only status key in resolveStatus ([70931b0](https://github.com/kdbsoft/dockercart/commit/70931b01adc7857d0497aaf36dd19e1f70d206de))
* load DB settings before ECB currency refresh ([e846ae2](https://github.com/kdbsoft/dockercart/commit/e846ae2058c45d4edaba441146538babccca8311))
* remove axis validation and lock options in form ([ec1ad31](https://github.com/kdbsoft/dockercart/commit/ec1ad31dc63974daa578e8a8de0d76f90128352e))
* sort option values by sort order and name ([75a1592](https://github.com/kdbsoft/dockercart/commit/75a15925a7bfbe5ba408f8bf1bb04040895f8896))
* update blog SEO URLs to ru-ua and drop ru-ru ([3ff3cb4](https://github.com/kdbsoft/dockercart/commit/3ff3cb4fb5b95433b8d1b5606ca76262bc286d84))

## [2.2.0](https://github.com/kdbsoft/dockercart/compare/v2.1.1...v2.2.0) (2026-07-19)

### Features

* add link type label and restyle banner form ([d2b31bc](https://github.com/kdbsoft/dockercart/commit/d2b31bc28722029caafb042e8a8e6e59357873a1))
* add multilingual product images, video and 3D model support ([cf85142](https://github.com/kdbsoft/dockercart/commit/cf8514279303b73fca1da0ef88858ebba9a0d3f1))
* add schema variant data for configurable products ([f2bef4a](https://github.com/kdbsoft/dockercart/commit/f2bef4ad8423d511802a624c056b84b1602f051b))

### Bug Fixes

* guard category column against undefined index ([da99f43](https://github.com/kdbsoft/dockercart/commit/da99f43cf11363fd4f332c3857430ad770165a6e))
* **i18n:** update ru-ua and uk-ua admin translations ([728ed15](https://github.com/kdbsoft/dockercart/commit/728ed15f95ebb938db3155c5240b61348fe65ac1))
* use variant data for product option display ([2f09973](https://github.com/kdbsoft/dockercart/commit/2f09973a63e4bfd9d304fcaf7ab648fdb68d68c8))

## [2.1.1](https://github.com/kdbsoft/dockercart/compare/v2.1.0...v2.1.1) (2026-07-18)

### Bug Fixes

* remove unused price_display column migration ([53ca9f6](https://github.com/kdbsoft/dockercart/commit/53ca9f6b68fb9ee74de3c91b0b3a351b713bce3b))

## [2.1.0](https://github.com/kdbsoft/dockercart/compare/v2.0.6...v2.1.0) (2026-07-17)

### Features

* add admin global search ([1f5bc50](https://github.com/kdbsoft/dockercart/commit/1f5bc50b0b5deeeeb3cbcf307c6ef579ba3d74f4))
* add configurable (variant-based) products ([7f0e8cd](https://github.com/kdbsoft/dockercart/commit/7f0e8cddaad25fa77bfd9b5c1dbc37ed1fb427ef))
* add multilingual tax support and fix image popover ([e5fad7c](https://github.com/kdbsoft/dockercart/commit/e5fad7cb29736ec69b5254a94bdead611617f7a7))
* add orders and customers to Manticore search ([0e5c15c](https://github.com/kdbsoft/dockercart/commit/0e5c15c47d56255b93a5b6660e1f38bed11a3c34))
* add status and categories columns to admin lists ([451072d](https://github.com/kdbsoft/dockercart/commit/451072d67d554931f5cfe82aa4cc90ad769d9659))
* allow assigning product images to color option values ([04fa6a4](https://github.com/kdbsoft/dockercart/commit/04fa6a47e3d210547694aec90691d3cbe1ca34e5))
* simplify admin tabs for products and categories ([706b2be](https://github.com/kdbsoft/dockercart/commit/706b2bee4c8418e60927e07263aa6a8cc31d5271))

### Bug Fixes

* add fatal error shutdown handler ([96cdf5a](https://github.com/kdbsoft/dockercart/commit/96cdf5a789f81870efcd013ecf628ff49bb4c40d))
* add IF NOT EXISTS to index creation in migration ([9f691bf](https://github.com/kdbsoft/dockercart/commit/9f691bf463d62b19f49a9578db7dab384a706f5a))
* add text_main_image language string for star button ([786c7b5](https://github.com/kdbsoft/dockercart/commit/786c7b596b9272bccf74b4db7ac0a2d09d3329dd))
* correct ru-UA translations ([e9494f9](https://github.com/kdbsoft/dockercart/commit/e9494f997671b96a7f647df39dd98ce0d6722109))
* fix gift autocomplete behavior and language typo ([f7ff58f](https://github.com/kdbsoft/dockercart/commit/f7ff58f3e5b4090e280fae8dc60af668d8226942))
* make image cards fully draggable ([ba93f26](https://github.com/kdbsoft/dockercart/commit/ba93f26f6289c11fe146b7e0519f9cdeed23221c))
* prioritize dcRecalcOptionPrice for price updates ([4edfa16](https://github.com/kdbsoft/dockercart/commit/4edfa16356eb5305400c490cd612503dd69636a4))

## [2.0.6](https://github.com/kdbsoft/dockercart/compare/v2.0.5...v2.0.6) (2026-07-14)

### Bug Fixes

* fix writability check for nested install paths ([acae9eb](https://github.com/kdbsoft/dockercart/commit/acae9ebef67beb1dbe3599b81f094e5f57cdcbbf))

## [2.0.5](https://github.com/kdbsoft/dockercart/compare/v2.0.4...v2.0.5) (2026-07-14)

### Bug Fixes

* DISALLOW_INDEXING param ([b534712](https://github.com/kdbsoft/dockercart/commit/b53471295c38775da40319ff6e31aab599cb4a32))

## [2.0.4](https://github.com/kdbsoft/dockercart/compare/v2.0.3...v2.0.4) (2026-07-14)

### Bug Fixes

* **ci:** fix RegistryPropertyReflectionExtension bugs and ignore propertyReadOnly ([c6651d1](https://github.com/kdbsoft/dockercart/commit/c6651d1cc8b25de65b5e42f0d8d23f1a18b535ea))

## [2.0.3](https://github.com/kdbsoft/dockercart/compare/v2.0.2...v2.0.3) (2026-07-14)

### Bug Fixes

* **ci:** resolve all PHPStan 2.2.5 errors ([0d7aee3](https://github.com/kdbsoft/dockercart/commit/0d7aee35fba2ee01cb06a9b831e279529687d5d5))

## [2.0.2](https://github.com/kdbsoft/dockercart/compare/v2.0.1...v2.0.2) (2026-07-14)

### Bug Fixes

* **ci:** mark config.php paths as optional in phpstan.neon ([613e371](https://github.com/kdbsoft/dockercart/commit/613e371c0a2c01334f57fbdc7349d10be8e54c6f))
* **ci:** update Lint workflow for php-cs-fixer/shim ([ee79d0f](https://github.com/kdbsoft/dockercart/commit/ee79d0f302d6395de150172bc0889128f9d02617))

## [2.0.1](https://github.com/kdbsoft/dockercart/compare/v2.0.0...v2.0.1) (2026-07-14)

### Bug Fixes

* **deps:** replace php-cs-fixer with shim to resolve phpstan conflict ([c89e149](https://github.com/kdbsoft/dockercart/commit/c89e149af6e86c3f24e93584811bd54ab31d506b))

## [2.0.0](https://github.com/kdbsoft/dockercart/compare/v1.48.0...v2.0.0) (2026-07-14)

### ⚠ BREAKING CHANGES

* switch to 2.0.0 version

### Code Refactoring

* switch to 2.0.0 version ([1e46ab9](https://github.com/kdbsoft/dockercart/commit/1e46ab91bb4130d12a9062433cfa230a303fedee))

## [1.48.0](https://github.com/kdbsoft/dockercart/compare/v1.47.7...v1.48.0) (2026-07-14)

### ⚠ BREAKING CHANGES

* Remove Memcached cache engine support
* switch default deployment mode to standalone

### Features

* add auto sync schedule and refactor sync CLI ([a3297c7](https://github.com/kdbsoft/dockercart/commit/a3297c79f54d9e916bb606bb00f1e52b6d5f9c37))
* add dev tooling, tests, and documentation ([f3a2b41](https://github.com/kdbsoft/dockercart/commit/f3a2b41d828ec8ef9af5080da89aa52f586584a3))
* add directory tree sidebar and thumbnail size controls ([47e11aa](https://github.com/kdbsoft/dockercart/commit/47e11aa5f001921a0e801f4f581b71d7d7c86365))
* add DISALLOW_INDEXING env var to block crawlers ([c6c57a6](https://github.com/kdbsoft/dockercart/commit/c6c57a62c36c32eff34764b6fdb2588fdb7f98cc))
* add Facebook Pixel analytics extension ([16e9723](https://github.com/kdbsoft/dockercart/commit/16e97235915350b3fcd620abb47c028ffaff74b7))
* add GDPR/CCPA privacy consent module ([2b4d8fc](https://github.com/kdbsoft/dockercart/commit/2b4d8fce26554f774359298d2d3ad0e6b9033f83))
* add Import/Feeds submenus to Catalog sidebar ([c5b8048](https://github.com/kdbsoft/dockercart/commit/c5b80482917d40d0b06b24822af71ff7af742487))
* add language switcher to admin header ([4d85fb5](https://github.com/kdbsoft/dockercart/commit/4d85fb5371699fa94793e713f6ba1f09ab37af7f))
* add license verification to extension store ([86245f7](https://github.com/kdbsoft/dockercart/commit/86245f75efb3141382ac4d5d363963f2502fdfb0))
* add licensing framework and migrations ([7fda79a](https://github.com/kdbsoft/dockercart/commit/7fda79ac3ef236499787e2209c209955de95a9c5))
* add log rotation with cron and logrotate ([7c30b97](https://github.com/kdbsoft/dockercart/commit/7c30b97ec8feab3e75bc00e34b4bf7d373aec530))
* add logo support for shipping extensions ([3c48df4](https://github.com/kdbsoft/dockercart/commit/3c48df4be05f6929708a7da73b12ff507bb88628))
* add math slider captcha module ([2c3ab99](https://github.com/kdbsoft/dockercart/commit/2c3ab99d5ca4a386f6760fe3cb0d0685b0bcf5f5))
* add Microsoft Clarity analytics extension ([450cfce](https://github.com/kdbsoft/dockercart/commit/450cfce14bf3054364b3b519507d25af886dbf41))
* add optional S3 backup worker ([ecc7a11](https://github.com/kdbsoft/dockercart/commit/ecc7a116ad14489c1ee2561c8eee365d1b43d733))
* add per-profile cron scheduling to feed modules ([0d65273](https://github.com/kdbsoft/dockercart/commit/0d65273fb94ca4c0cc72210d5b6061261d687b15))
* add reset and view-all for customer activity ([28b4615](https://github.com/kdbsoft/dockercart/commit/28b4615d7cb66df4e2200fb474a98beea961371b))
* add scheduled sitemap generation ([a95de8b](https://github.com/kdbsoft/dockercart/commit/a95de8b7eb8c6084d899cb0183265e04f66b6d50))
* add token authentication to healthcheck ([6514375](https://github.com/kdbsoft/dockercart/commit/6514375c8bf5ee8ecf83b5e9c13b047214b37be7))
* add universal scheduler and admin UI ([5432681](https://github.com/kdbsoft/dockercart/commit/543268187e65762b3ca48a7fe1ba201ef0d48a0b))
* add upload storage dir for backup role ([8b40c1e](https://github.com/kdbsoft/dockercart/commit/8b40c1e809573d05c1c747fc49b34d3a50e142b8))
* add vendor JS/CSS assets for admin and catalog ([0dd2a14](https://github.com/kdbsoft/dockercart/commit/0dd2a14d1a5253110b4dd17280cced6cc706491c))
* Add writability check to extension store installer ([8fc7d28](https://github.com/kdbsoft/dockercart/commit/8fc7d2845e46d902ecf22d17f83cdab04c18e97e))
* **admin:** add extension store ([4ce68d5](https://github.com/kdbsoft/dockercart/commit/4ce68d5b6f7012360777d1b6b36f8fe641315db7))
* allow manual license key entry for store extensions ([e844f97](https://github.com/kdbsoft/dockercart/commit/e844f97d450f5be8973038cb5e39442174a10c4c))
* bundle vendor deps and generate robots.txt ([2d61982](https://github.com/kdbsoft/dockercart/commit/2d61982faa1dbb22b19d512ea618d516f5aed59c))
* check file writability before overwriting ([5c51794](https://github.com/kdbsoft/dockercart/commit/5c517948d427bce822a6aac3d8882f300c979a92))
* convert sitemap feed to CLI and scheduler ([5f6b85c](https://github.com/kdbsoft/dockercart/commit/5f6b85c5351fe07c5659bd52bd0530ad850d3214))
* integrate dockercart_gdpr module ([1090361](https://github.com/kdbsoft/dockercart/commit/10903619db70b263a9dbdfffcaca7fc621bd0151))
* license system WIP ([a3142e6](https://github.com/kdbsoft/dockercart/commit/a3142e60d2c8ac597123e1e167a22d833c2c1df1))
* localize admin menu items ([b9d6bef](https://github.com/kdbsoft/dockercart/commit/b9d6bef942f29aa2e1c76dc65e313458f2e13c49))
* log search queries on autocomplete click ([cc006c9](https://github.com/kdbsoft/dockercart/commit/cc006c92eb1d533026bcd6bff6c121078110d968))
* make license key optional for module install ([ff3c67e](https://github.com/kdbsoft/dockercart/commit/ff3c67e12dd493cc086b75104a66b8523dab7c65))
* make timezone configurable via TZ env var ([9f875d5](https://github.com/kdbsoft/dockercart/commit/9f875d51f6f98c30bbfa11f36a50b2ed24bced9e))
* migrate import/export cron to CLI runner ([478a8a1](https://github.com/kdbsoft/dockercart/commit/478a8a1ae21831e84677b365678d8ed4d0e0a41c))
* remove built-in Google Base feed module ([2832c53](https://github.com/kdbsoft/dockercart/commit/2832c53aab64dec11d4b1724a704b3c651179a77))
* remove Fixer Currency Converter extension ([33e8495](https://github.com/kdbsoft/dockercart/commit/33e8495e5d7e10fdde01ca05cce5dacab59baa6f))
* remove Google Shopping extension ([dd721d8](https://github.com/kdbsoft/dockercart/commit/dd721d850517d8316f0dbe22f24ee3773e952f9a))
* remove license verification from sitemap module ([cfb9e9e](https://github.com/kdbsoft/dockercart/commit/cfb9e9ee7b404890656cc1ec557296f86369706f))
* Remove Memcached cache engine support ([0d0db3a](https://github.com/kdbsoft/dockercart/commit/0d0db3a203703ac53c2e0e9efe5732133f63e7cc))
* **store:** display changelog in extension details modal ([ca55dae](https://github.com/kdbsoft/dockercart/commit/ca55dae9e3485395039d635273c27ede20169ba2))
* Sync store URL from env to database ([ccd8d64](https://github.com/kdbsoft/dockercart/commit/ccd8d64290c9bf615a0bba251d2bc233e7a04e0d))
* use DockercartLicensing, update modules ([ff75429](https://github.com/kdbsoft/dockercart/commit/ff75429bf559c31b00f5672212bd8754b4fccce8))
* use scheduler for YML feed generation ([d154ea6](https://github.com/kdbsoft/dockercart/commit/d154ea6d6ea1970dfc67e1c932b6273701453ad8))

### Bug Fixes

* add approval buttons and messages ([f6c51a3](https://github.com/kdbsoft/dockercart/commit/f6c51a3b9cf6169031d3f5ae645204dcece16613))
* add heading styles for store modal description ([63ac6fb](https://github.com/kdbsoft/dockercart/commit/63ac6fbef377cc712c5a350aab6f4660bac5c7a9))
* banner grid layout and slideshow styles ([c041d02](https://github.com/kdbsoft/dockercart/commit/c041d021067c83a0bb53542b7068c3034883c1c4))
* correct ru-ua translation for 'disabled' ([2490f1b](https://github.com/kdbsoft/dockercart/commit/2490f1be00ba2c6c8fef37e979d1bbdcba50c5e4))
* exclude zero-status orders from chart queries ([939c6f2](https://github.com/kdbsoft/dockercart/commit/939c6f2b8ad3fb83e472774d4ccfbfe6040670ff))
* **export_yml:** remove .htaccess fallback generation ([5a083b1](https://github.com/kdbsoft/dockercart/commit/5a083b183ef6f7ec764b49ac5dbd370c397208c1))
* handle empty currency symbol in chart ([fdc728b](https://github.com/kdbsoft/dockercart/commit/fdc728bee63ed15da40bd35e4b6e64c4c7478b92))
* hide zero-price option values on product page ([0601193](https://github.com/kdbsoft/dockercart/commit/0601193e05d19bee236098af240d30690069305e))
* prevent undefined index in blog comment view ([e835b47](https://github.com/kdbsoft/dockercart/commit/e835b475f2a36540216a117e39057e0371dee17e))
* prevent uninstall of active modules ([a12fefa](https://github.com/kdbsoft/dockercart/commit/a12fefaa37f618b8cc8505eb4710b80531ca01aa))
* purge extension install records and data ([ec4efa5](https://github.com/kdbsoft/dockercart/commit/ec4efa59289689b4a35dc5a104c65bd9d3ae4ef6))
* remove redundant clean dependency on down ([352d5dc](https://github.com/kdbsoft/dockercart/commit/352d5dcf9f13f7039e9808aa39e7c263dee8e39e))
* reorder unignore rules for image files ([4bfa7b6](https://github.com/kdbsoft/dockercart/commit/4bfa7b61596aff246b00b410deefdca1d0d8923f))
* replace rename with copy+unlink in extension install ([b3ddbf6](https://github.com/kdbsoft/dockercart/commit/b3ddbf687d919c014f3e72bd99084421cace0b13))
* reset viewed product cache and detail table ([c66bdd5](https://github.com/kdbsoft/dockercart/commit/c66bdd546621808b43395389575d7ead8cab1a47))
* reverse extension install order and date format ([65e8a57](https://github.com/kdbsoft/dockercart/commit/65e8a57e122cc5d0a831f129912ede20feb0868a))
* revise currency refresh schedule and seed data ([11c0f96](https://github.com/kdbsoft/dockercart/commit/11c0f9623092d9f20ba3063e29fc62585607e951))
* scope curl resolve to developer domain ([a8bf1d4](https://github.com/kdbsoft/dockercart/commit/a8bf1d4ba9b8104f31e7f97b87f201fb08a4a3ba))
* show option price when value is zero ([9d0c6bc](https://github.com/kdbsoft/dockercart/commit/9d0c6bc579edda2eb8b4776b71f98df0cd95897c))
* skip auto-populate for test domains ([57d8bf4](https://github.com/kdbsoft/dockercart/commit/57d8bf43086ca9ef1c446fddc0b999918e4435bb))
* toggle directory tree navigation on row click ([1f0cd59](https://github.com/kdbsoft/dockercart/commit/1f0cd597afacb5bef65ba2ed68047df9e73cf09b))
* use delegated event binding for file manager ([d4dc041](https://github.com/kdbsoft/dockercart/commit/d4dc041804322ba32b5e226e4d6a5acb32b8c052))
* use exact setting code and key in migration ([aa201af](https://github.com/kdbsoft/dockercart/commit/aa201af03bcd59f925db11a299bc97825db41bae))
* use production endpoints for licensing and store ([97ec846](https://github.com/kdbsoft/dockercart/commit/97ec846e8e8f76604d115cbd890aaf70db231852))

### Code Refactoring

* switch default deployment mode to standalone ([b6ae310](https://github.com/kdbsoft/dockercart/commit/b6ae310fb17e90b3fc38eba3394122eaaa37ed67))

## [1.47.7](https://github.com/mathflow-bit/dockercart/compare/v1.47.6...v1.47.7) (2026-06-27)

### Bug Fixes

* tighten config file ownership and permissions ([e989dbd](https://github.com/mathflow-bit/dockercart/commit/e989dbd3eef1fd61fbe69e54d83cd2c02034ff1a))
* tighten Let's Encrypt private key permissions ([f1b46e0](https://github.com/mathflow-bit/dockercart/commit/f1b46e008dea82d9737e0c00f5f723cd5d942e60))

## [1.47.6](https://github.com/mathflow-bit/dockercart/compare/v1.47.5...v1.47.6) (2026-06-24)

### Bug Fixes

* add preorder support to product listings and schema ([31901bf](https://github.com/mathflow-bit/dockercart/commit/31901bf0e1c244120efb3d5d764d3f8d6bcb33e1))

## [1.47.5](https://github.com/mathflow-bit/dockercart/compare/v1.47.4...v1.47.5) (2026-06-24)

### Bug Fixes

* add preorder label for zero-stock products ([f3a6572](https://github.com/mathflow-bit/dockercart/commit/f3a6572e61b88b0a6477c9e5e7864d58430e33c8))

## [1.47.4](https://github.com/mathflow-bit/dockercart/compare/v1.47.3...v1.47.4) (2026-06-24)

### Bug Fixes

* block out-of-stock product additions ([25c4aff](https://github.com/mathflow-bit/dockercart/commit/25c4affbf066bec3482306e93746c99754965f35))
* **filemanager:** refine upload error handling ([956ef75](https://github.com/mathflow-bit/dockercart/commit/956ef75d85ce3e33e6d0332c7cc652d7a5a1732c))

## [1.47.3](https://github.com/mathflow-bit/dockercart/compare/v1.47.2...v1.47.3) (2026-06-22)

### Bug Fixes

* add debug logging to WayforPay callback handler ([6d129e7](https://github.com/mathflow-bit/dockercart/commit/6d129e78b4ecdc9c929bdaab04f34459d50c79ef))

## [1.47.2](https://github.com/mathflow-bit/dockercart/compare/v1.47.1...v1.47.2) (2026-06-22)

### Bug Fixes

* quickfix ([6ca2b1c](https://github.com/mathflow-bit/dockercart/commit/6ca2b1c3cc91722f537165f721f95df8790d2229))
* use number_format for WayForPay amounts ([2bc6ee6](https://github.com/mathflow-bit/dockercart/commit/2bc6ee6b807a14b79637f4ea13f8357a308e373e))

## [1.47.1](https://github.com/mathflow-bit/dockercart/compare/v1.47.0...v1.47.1) (2026-06-22)

### Bug Fixes

* Load payment extension for pre-selected ([2f65516](https://github.com/mathflow-bit/dockercart/commit/2f65516590a66e458c7a6b5bbb8ef39941a86135))

## [1.47.0](https://github.com/mathflow-bit/dockercart/compare/v1.46.0...v1.47.0) (2026-06-22)

### Features

* Add WayForPay payment module ([9de409c](https://github.com/mathflow-bit/dockercart/commit/9de409c9a53f6d46cf39b2a5a9df04bf509f9c69))

### Bug Fixes

* suppress mail() warnings ([238cc9f](https://github.com/mathflow-bit/dockercart/commit/238cc9f7d9d6a949c30d857a3b8339d655ba73b0))

## [1.46.0](https://github.com/mathflow-bit/dockercart/compare/v1.45.12...v1.46.0) (2026-06-20)

### Features

* add modification add/edit form and test ([aa1bfb4](https://github.com/mathflow-bit/dockercart/commit/aa1bfb4d60c8cad3084ee420bd0b381b0b7464a7))

### Bug Fixes

* prevent double conversion in multicurrency ([f242036](https://github.com/mathflow-bit/dockercart/commit/f242036af1781f0e0ee5d1d59a94074eb79d2aac))
* skip currency conversion when formatting special price ([7a199fc](https://github.com/mathflow-bit/dockercart/commit/7a199fccb2f98977a38a0558e7c62a9fc4d268a4))

## [1.45.12](https://github.com/mathflow-bit/dockercart/compare/v1.45.11...v1.45.12) (2026-06-20)

### Bug Fixes

* return formatted price for default currency ([8aa289b](https://github.com/mathflow-bit/dockercart/commit/8aa289b55aff0fbe4b988e70aa8b465371154099))

## [1.45.11](https://github.com/mathflow-bit/dockercart/compare/v1.45.10...v1.45.11) (2026-06-20)

### Bug Fixes

* Use stored currency for price display ([9c8c056](https://github.com/mathflow-bit/dockercart/commit/9c8c056ace4aa839ed1f63d7f029b5244c658aa5))

## [1.45.10](https://github.com/mathflow-bit/dockercart/compare/v1.45.9...v1.45.10) (2026-06-20)

### Bug Fixes

* add pending order status to admin dashboard ([6b5b08d](https://github.com/mathflow-bit/dockercart/commit/6b5b08d38d4fe280d7a7224a461e0beb159ac448))
* correct bot detection logic and indentation ([6289f66](https://github.com/mathflow-bit/dockercart/commit/6289f66803aa9307e2f509b08fda514b55f10d31))
* correct stats sidebar permission check ([61e5ea2](https://github.com/mathflow-bit/dockercart/commit/61e5ea280b71c521593cf7e32de255b8fdcd2ac8))
* restrict API IP check to API ID ([722028e](https://github.com/mathflow-bit/dockercart/commit/722028ed37aea2cd57bce56c3f54ab4b35b15d8c))

## [1.45.9](https://github.com/mathflow-bit/dockercart/compare/v1.45.8...v1.45.9) (2026-06-19)

### Bug Fixes

* standardize dashboard totalValue output formats ([ba944c7](https://github.com/mathflow-bit/dockercart/commit/ba944c760dae8860d428b906839fccbe1ed04691))

## [1.45.8](https://github.com/mathflow-bit/dockercart/compare/v1.45.7...v1.45.8) (2026-06-19)

### Bug Fixes

* ensure images are rebuilt in standalone modes ([0356aae](https://github.com/mathflow-bit/dockercart/commit/0356aae43a59422e773bd5bf54af962b6b04eddb))

## [1.45.7](https://github.com/mathflow-bit/dockercart/compare/v1.45.6...v1.45.7) (2026-06-19)

### Bug Fixes

* add CHOWN capability to service containers ([da0de80](https://github.com/mathflow-bit/dockercart/commit/da0de8066c2ef05410499ab726cd1dd39401c1c5))

## [1.45.6](https://github.com/mathflow-bit/dockercart/compare/v1.45.5...v1.45.6) (2026-06-19)

### Bug Fixes

* use integer abbreviation for dashboard totals ([0e86504](https://github.com/mathflow-bit/dockercart/commit/0e865048c61a63c1cb2c4af7ef265020098ee67c))

## [1.45.5](https://github.com/mathflow-bit/dockercart/compare/v1.45.4...v1.45.5) (2026-06-19)

### Bug Fixes

* add DAC_OVERRIDE capability ([3a83823](https://github.com/mathflow-bit/dockercart/commit/3a838230dcd3eed53a8ce01bc812a07b5595ddd0))

## [1.45.4](https://github.com/mathflow-bit/dockercart/compare/v1.45.3...v1.45.4) (2026-06-19)

### Bug Fixes

* clean up standalone Makefile targets ([c581607](https://github.com/mathflow-bit/dockercart/commit/c58160779b1ca0427ef1a909f62c76815e5774fe))

## [1.45.3](https://github.com/mathflow-bit/dockercart/compare/v1.45.2...v1.45.3) (2026-06-19)

### Bug Fixes

* use nginx -s reload for cert renewal ([98ea90c](https://github.com/mathflow-bit/dockercart/commit/98ea90c2727e0fae63126226345fe70b2313d0d5))

## [1.45.2](https://github.com/mathflow-bit/dockercart/compare/v1.45.1...v1.45.2) (2026-06-17)

### Bug Fixes

* show category description when empty ([34b1ae1](https://github.com/mathflow-bit/dockercart/commit/34b1ae11af639686de262812e976a55154f181cf))

## [1.45.1](https://github.com/mathflow-bit/dockercart/compare/v1.45.0...v1.45.1) (2026-06-17)

### Bug Fixes

* add compose function for standalone mode ([95ae424](https://github.com/mathflow-bit/dockercart/commit/95ae4241ceb83ce3ff53baeb7071c88464415549))

## [1.45.0](https://github.com/mathflow-bit/dockercart/compare/v1.44.5...v1.45.0) (2026-06-17)

### Features

* add Redis session support and harden security ([3e98d81](https://github.com/mathflow-bit/dockercart/commit/3e98d814a95efb6678568918746e50c7f138b736))

## [1.44.5](https://github.com/mathflow-bit/dockercart/compare/v1.44.4...v1.44.5) (2026-06-09)

### Bug Fixes

* add Let's Encrypt compose file to standalone update script ([81386b0](https://github.com/mathflow-bit/dockercart/commit/81386b03c6afc5267baa0afbbf5b39b263cd007f))

## [1.44.4](https://github.com/mathflow-bit/dockercart/compare/v1.44.3...v1.44.4) (2026-06-09)

### Bug Fixes

* return actual affected rows count ([d43ef50](https://github.com/mathflow-bit/dockercart/commit/d43ef507189e1dbd21b2df4a3a72505b38865ed7))

## [1.44.3](https://github.com/mathflow-bit/dockercart/compare/v1.44.2...v1.44.3) (2026-06-09)

### Bug Fixes

* skip unchanged meta updates and correct batch termination ([7e5b4fc](https://github.com/mathflow-bit/dockercart/commit/7e5b4fcabef445796ab0fa93394b75728a75ceba))

## [1.44.2](https://github.com/mathflow-bit/dockercart/compare/v1.44.1...v1.44.2) (2026-06-09)

### Bug Fixes

* add color swatch columns to option value ([2ba7b53](https://github.com/mathflow-bit/dockercart/commit/2ba7b53805381050f5c8463ecb07d7d8bcb53deb))
* increase product image display dimensions ([e4de77a](https://github.com/mathflow-bit/dockercart/commit/e4de77a824d4bb67495c4a56984a084305aec34f))

## [1.44.1](https://github.com/mathflow-bit/dockercart/compare/v1.44.0...v1.44.1) (2026-06-09)

### Bug Fixes

* replace thumb size with orientation-based display ([dc4e236](https://github.com/mathflow-bit/dockercart/commit/dc4e23602fa6acf13a99bd33602521fda77f7ea5))

## [1.44.0](https://github.com/mathflow-bit/dockercart/compare/v1.43.0...v1.44.0) (2026-06-09)

### Features

* add image orientation detection for product gallery ([bae32a7](https://github.com/mathflow-bit/dockercart/commit/bae32a7bb2478c87d5167b416bf8cd40c2c8ec1d))

### Bug Fixes

* extend payment button handler with 3DS and fallback ([e0cfe57](https://github.com/mathflow-bit/dockercart/commit/e0cfe570d968c8e2315db9ac51766eb1659c3a89))

## [1.43.0](https://github.com/mathflow-bit/dockercart/compare/v1.42.6...v1.43.0) (2026-06-08)

### Features

* add back-to-parent link in category & search ([ea799ca](https://github.com/mathflow-bit/dockercart/commit/ea799ca8e0bcdc69642bbfe3c2717a55f1baa5f0))
* add blog post recommendations ([6a2dbb0](https://github.com/mathflow-bit/dockercart/commit/6a2dbb0dde7595d16151871af3d5cb8ab360d172))
* add category icons to navigation menus ([13cf041](https://github.com/mathflow-bit/dockercart/commit/13cf041cb98ad877bc6b4a55d63a7d9e5d7fc299))
* add color option type ([eaa976a](https://github.com/mathflow-bit/dockercart/commit/eaa976ad5030c2a6f91fbcfa7b5da720fd0855f7))
* add content hash to image cache filenames ([41cb6c6](https://github.com/mathflow-bit/dockercart/commit/41cb6c64632fe22ec9989912fd93fb78729883cf))
* add error summary and fix checkbox escape ([12afc1f](https://github.com/mathflow-bit/dockercart/commit/12afc1f15d8e083f81325c50cf12439fabbbf515))
* add icon field to categories ([f5ecf53](https://github.com/mathflow-bit/dockercart/commit/f5ecf53f9a4a5acd24d928f817a7e77b5c621ba7))
* Add is_hit flag to product option values ([dcf7990](https://github.com/mathflow-bit/dockercart/commit/dcf79900d9505a8e2ca130ed570f307acd665fa2))
* add manual product selection and category filter ([126acf7](https://github.com/mathflow-bit/dockercart/commit/126acf7b01a1d27dfb551b9c815263a985e13f4a))
* add multi-language support for module names ([8c3f30f](https://github.com/mathflow-bit/dockercart/commit/8c3f30f82f676184b9080801822a348c62a67990))
* add refine categories to manufacturer page ([ae0ac72](https://github.com/mathflow-bit/dockercart/commit/ae0ac726ed444ec7beab81a2e9e6fbc2aec2c678))
* add SVG support to filemanager and image tools ([5aed217](https://github.com/mathflow-bit/dockercart/commit/5aed217b596f6364977702a2206b022470f81eee))
* replace category dropdown with refine widget ([3ce6690](https://github.com/mathflow-bit/dockercart/commit/3ce6690144c42b74a5812ae9d26a63a723b5b994))

### Bug Fixes

* add coupon, reward, shipping, voucher total templates ([6c06c2d](https://github.com/mathflow-bit/dockercart/commit/6c06c2dc452d4c693cd2db41c85ad15b8280d219))
* correct group ownership from www-data to staff in entrypoint ([56364fb](https://github.com/mathflow-bit/dockercart/commit/56364fb8c0e81a512754628f5928c38ee16e86a5))
* correct RU/UA translations for latest module ([7f62bcd](https://github.com/mathflow-bit/dockercart/commit/7f62bcdd2d40ffd9e7c7be39cbadf2957d8b4ec1))
* **migrations:** add IF NOT EXISTS and remove obsolete ([f3c344a](https://github.com/mathflow-bit/dockercart/commit/f3c344aecc19f2825b2aa923fac0e530870da080))
* remove sticky summary scroll constraints ([7076526](https://github.com/mathflow-bit/dockercart/commit/7076526a0fb772fedfd51fcd1a553018d27efcbc))
* stop toggling maintenance mode on mod refresh ([b0ec8d7](https://github.com/mathflow-bit/dockercart/commit/b0ec8d7d39444b0bd281bf0c8edbd44579eb7956))
* use config_complete_status in sale dashboard ([5e8435f](https://github.com/mathflow-bit/dockercart/commit/5e8435fcd17d3326f1aded6f7403638c599e9229))
* use store base currency in dashboard chart ([55947d3](https://github.com/mathflow-bit/dockercart/commit/55947d3a39c372665c316f0a9ff146fbe865565f))

## [1.42.6](https://github.com/mathflow-bit/dockercart/compare/v1.42.5...v1.42.6) (2026-06-06)

### Bug Fixes

* update modification cache owner after refresh ([d294b7f](https://github.com/mathflow-bit/dockercart/commit/d294b7fae9ee463e41c1f28225d7731c0729b683))

## [1.42.5](https://github.com/mathflow-bit/dockercart/compare/v1.42.4...v1.42.5) (2026-06-06)

### Bug Fixes

* avoid duplicate address in NovaPost division ([c23e961](https://github.com/mathflow-bit/dockercart/commit/c23e96133fb3cff2becbf87b63883811bf5d5c2b))
* update healthcheck URL to /health ([25f26a0](https://github.com/mathflow-bit/dockercart/commit/25f26a0ea6aad797b03b7bb49f66acbb3586aa58))

## [1.42.4](https://github.com/mathflow-bit/dockercart/compare/v1.42.3...v1.42.4) (2026-06-06)

## [1.42.3](https://github.com/mathflow-bit/dockercart/compare/v1.42.2...v1.42.3) (2026-06-04)

### Bug Fixes

* ensure banner link spans full width ([6f41ee1](https://github.com/mathflow-bit/dockercart/commit/6f41ee1497565ecbe3f70ad89c61c735588cf9f5))

## [1.42.2](https://github.com/mathflow-bit/dockercart/compare/v1.42.1...v1.42.2) (2026-06-04)

### Bug Fixes

* **tailwind:** safelist dynamic shop feature palette ([17f877c](https://github.com/mathflow-bit/dockercart/commit/17f877c2b32dee81938369f7bb532adffd739a0c))

## [1.42.1](https://github.com/mathflow-bit/dockercart/compare/v1.42.0...v1.42.1) (2026-06-04)

### Bug Fixes

* add cache-busting to Nova Post assets ([dc3437c](https://github.com/mathflow-bit/dockercart/commit/dc3437c7843257367312d6a4cc180998107f7833))
* use product currency for special and edit ([0351c4b](https://github.com/mathflow-bit/dockercart/commit/0351c4bda5f7d709fa7ecb290da0bdbd227d30c7))

## [1.42.0](https://github.com/mathflow-bit/dockercart/compare/v1.41.0...v1.42.0) (2026-06-04)

### Features

* add SEO Description module ([d5ca1ea](https://github.com/mathflow-bit/dockercart/commit/d5ca1eac0fbf3d6c6773246c5429b5d6c5ff2056))

### Bug Fixes

* add name filter for admin information ([1ada604](https://github.com/mathflow-bit/dockercart/commit/1ada60420aaa425bec85aec2357dd5d55db79523))
* always sync payment address for same-as-shipping ([fb4cde0](https://github.com/mathflow-bit/dockercart/commit/fb4cde06f1db95c91a62571cc724d7f36367c95c))
* expand NP to Nova Poshta in Ukrainian strings ([fae5dcf](https://github.com/mathflow-bit/dockercart/commit/fae5dcffbf39588b9ace39e86c5fdad70a00b9c4))

## [1.41.0](https://github.com/mathflow-bit/dockercart/compare/v1.40.3...v1.41.0) (2026-06-04)

### Features

* add default region mappings and improve city detection to NovaPost ([ec02568](https://github.com/mathflow-bit/dockercart/commit/ec0256898e024ab84d7c680507fcadfb34d1b4b7))

## [1.40.3](https://github.com/mathflow-bit/dockercart/compare/v1.40.2...v1.40.3) (2026-06-04)

### Bug Fixes

* handle zero cart weight in novapost shipping ([6ed5d2a](https://github.com/mathflow-bit/dockercart/commit/6ed5d2a4a7b07f9a9bdf3b8a3dff22ac76d67313))

## [1.40.2](https://github.com/mathflow-bit/dockercart/compare/v1.40.1...v1.40.2) (2026-06-04)

### Bug Fixes

* add deps target and composer.lock hash check ([9a8c2ec](https://github.com/mathflow-bit/dockercart/commit/9a8c2ec7e5cf0da3f07a06bc17a0fc6b6e2d7e6d))

## [1.40.1](https://github.com/mathflow-bit/dockercart/compare/v1.40.0...v1.40.1) (2026-06-03)

### Bug Fixes

* correct banner link parsing and resolution ([dd8433f](https://github.com/mathflow-bit/dockercart/commit/dd8433fd987c3f12da55b94228af4a06a5a72759))

## [1.40.0](https://github.com/mathflow-bit/dockercart/compare/v1.39.0...v1.40.0) (2026-06-03)

### Features

* add layout builder for visual module management ([18abe05](https://github.com/mathflow-bit/dockercart/commit/18abe054309555d4f2bb6ff9188131551070f361))
* add structured link types to banner slides ([67baa76](https://github.com/mathflow-bit/dockercart/commit/67baa76757ec8bb1eacf79ce711e8cb8d9ac5ca7))
* banner & layour localisation id admin ([09741f6](https://github.com/mathflow-bit/dockercart/commit/09741f6f9cb66cbd8db5aacb5662c314291416f0))
* builder improvements ([caafc58](https://github.com/mathflow-bit/dockercart/commit/caafc584597827a8bf39cc7482e7561b97109479))
* display permission titles in user group form ([a27f22b](https://github.com/mathflow-bit/dockercart/commit/a27f22bf3b771371bbdd0ac518a747d84d514a14))
* simplify banner links to one per slide ([87c255c](https://github.com/mathflow-bit/dockercart/commit/87c255c1cf5497899c153ae272ba703780e607bb))
* skip product view tracking for known bots ([d511b5e](https://github.com/mathflow-bit/dockercart/commit/d511b5ea824ad11675cf8cc07ddaafe2879f9bc5))

### Bug Fixes

* add missing input-group wrapper in layout module form ([abf6ceb](https://github.com/mathflow-bit/dockercart/commit/abf6cebb26a71f9b6c7e88f680d5f5769a7ebed3))
* disable collapsed banners drag&drop in admin ([07cdc08](https://github.com/mathflow-bit/dockercart/commit/07cdc088cf13c4f91f7238270b7e91ce894a1a03))

## [1.39.0](https://github.com/mathflow-bit/dockercart/compare/v1.38.1...v1.39.0) (2026-06-02)

### Features

* add product info modal with extended details to order view ([6c4543f](https://github.com/mathflow-bit/dockercart/commit/6c4543f11a61d5390fd47d40e7146ddfc6ebb8b2))

## [1.38.1](https://github.com/mathflow-bit/dockercart/compare/v1.38.0...v1.38.1) (2026-06-02)

### Bug Fixes

* add cascade checking for category checkboxes in product form ([4be6d68](https://github.com/mathflow-bit/dockercart/commit/4be6d6874e680254421b4b5ee06dd7b66ce4d85a))

## [1.38.0](https://github.com/mathflow-bit/dockercart/compare/v1.37.4...v1.38.0) (2026-06-02)

### Features

* add category tree selector for products ([2a5c8f8](https://github.com/mathflow-bit/dockercart/commit/2a5c8f82ce7775f3c9abc0c03e3011e666d18f57))

## [1.37.4](https://github.com/mathflow-bit/dockercart/compare/v1.37.3...v1.37.4) (2026-06-02)

### Bug Fixes

* sanitize extension names in extensions list view ([ff77e21](https://github.com/mathflow-bit/dockercart/commit/ff77e218f157158e582bfb912071d6ca301dbef7))

## [1.37.3](https://github.com/mathflow-bit/dockercart/compare/v1.37.2...v1.37.3) (2026-06-02)

### Bug Fixes

* remove raw filter from custom CSS and JS fields ([531a229](https://github.com/mathflow-bit/dockercart/commit/531a2293141e52086a4f654e34407eede6b25744))

## [1.37.2](https://github.com/mathflow-bit/dockercart/compare/v1.37.1...v1.37.2) (2026-06-02)

### Bug Fixes

* render raw custom CSS and JS in theme settings ([9da6987](https://github.com/mathflow-bit/dockercart/commit/9da69874a00e024e3bf6e3e46578e82c0b8ec0ac))

## [1.37.1](https://github.com/mathflow-bit/dockercart/compare/v1.37.0...v1.37.1) (2026-06-02)

### Bug Fixes

* preserve PNG and WebP transparency when saving and cropping images ([ab9aaa7](https://github.com/mathflow-bit/dockercart/commit/ab9aaa708eeea3527781ff2c65100a0f62bec3cd))

## [1.37.0](https://github.com/mathflow-bit/dockercart/compare/v1.36.0...v1.37.0) (2026-06-02)

### Features

* add custom CSS and JavaScript fields to DockerCart theme ([c457841](https://github.com/mathflow-bit/dockercart/commit/c4578412fd05be1d7b5e7eb697a6a5865c9ed4a4))

### Bug Fixes

* add skip_webp option to image resize and enable it in favicon ([2b8b7f6](https://github.com/mathflow-bit/dockercart/commit/2b8b7f69acdb4a4aa0d7cdedc7848692c982668f))

## [1.36.0](https://github.com/mathflow-bit/dockercart/compare/v1.35.0...v1.36.0) (2026-06-01)

### Features

* add language selector to admin login and support session language ([7998098](https://github.com/mathflow-bit/dockercart/commit/7998098276ba763e4c6420f330fd63581da996bc))

## [1.35.0](https://github.com/mathflow-bit/dockercart/compare/v1.34.5...v1.35.0) (2026-06-01)

### Features

* add copy actions for catalog and blog entities ([206918e](https://github.com/mathflow-bit/dockercart/commit/206918e70a46e51934f088d3ffa5ad36a4ed97f2))

### Bug Fixes

* add multicurrency price fix events for catalog product queries ([3b0aace](https://github.com/mathflow-bit/dockercart/commit/3b0aace5c057e71111aea836ae2ab11385e86da6))
* product copy uniqueness and option handling ([29a93d5](https://github.com/mathflow-bit/dockercart/commit/29a93d560092c78344547872c0f481f8c301fa20))

## [1.34.5](https://github.com/mathflow-bit/dockercart/compare/v1.34.4...v1.34.5) (2026-05-30)

### Bug Fixes

* improve mobile layout of admin login ([d0cd777](https://github.com/mathflow-bit/dockercart/commit/d0cd7770a61ee855116bd9c3750613a8d5ceba97))

## [1.34.4](https://github.com/mathflow-bit/dockercart/compare/v1.34.3...v1.34.4) (2026-05-30)

### Bug Fixes

* add mobile filter button bar for responsive UI ([be68be1](https://github.com/mathflow-bit/dockercart/commit/be68be1d04ffeca659c3e14c5a3fb210646bc40b))

## [1.34.3](https://github.com/mathflow-bit/dockercart/compare/v1.34.2...v1.34.3) (2026-05-30)

### Bug Fixes

* filter dockercart queries by current language ([8292aa1](https://github.com/mathflow-bit/dockercart/commit/8292aa11eb7be19aa0924fcefca5876718d8c4b0))

## [1.34.2](https://github.com/mathflow-bit/dockercart/compare/v1.34.1...v1.34.2) (2026-05-30)

## [1.34.1](https://github.com/mathflow-bit/dockercart/compare/v1.34.0...v1.34.1) (2026-05-30)

### Bug Fixes

* filter active languages and skip hreflang when only one language ([1ab0fa9](https://github.com/mathflow-bit/dockercart/commit/1ab0fa9eaa7d91730e0d7f1f34ddc6853772360e))

## [1.34.0](https://github.com/mathflow-bit/dockercart/compare/v1.33.0...v1.34.0) (2026-05-30)

### Features

* add inline SEO URL generator and refine SEO URL handling ([b747d39](https://github.com/mathflow-bit/dockercart/commit/b747d39cddf4f0200778d69fcad80bcd719f06a1))

### Bug Fixes

* display inline translation errors as admin UI alerts ([2c2168a](https://github.com/mathflow-bit/dockercart/commit/2c2168aec0b631565f9c314e17eb1e621e67f6b4))

## [1.33.0](https://github.com/mathflow-bit/dockercart/compare/v1.32.0...v1.33.0) (2026-05-30)

### Features

* add preorder handling and rename localisation labels ([7b88568](https://github.com/mathflow-bit/dockercart/commit/7b88568d22138701e1609ce663bdb6a5808d2d57))
* add preorder support for products and update related UI and logic ([18eadca](https://github.com/mathflow-bit/dockercart/commit/18eadca90a5c555cff521af255dfcf958d3daa86))
* enhance dashboard periods, add month range and conditional change ([7254707](https://github.com/mathflow-bit/dockercart/commit/7254707581a1da4e7f9213bcd958deeec3927838))

### Bug Fixes

* remove default config_encryption value from DB dump ([12a6db8](https://github.com/mathflow-bit/dockercart/commit/12a6db8f39baf43807774c822dd9665ea8704d20))

## [1.32.0](https://github.com/mathflow-bit/dockercart/compare/v1.31.1...v1.32.0) (2026-05-29)

### Features

* add custom MySQL config to compose files ([ed47f3c](https://github.com/mathflow-bit/dockercart/commit/ed47f3c9d1b92037d336e68251b3fbed1c3a61a6))

## [1.31.1](https://github.com/mathflow-bit/dockercart/compare/v1.31.0...v1.31.1) (2026-05-29)

### Bug Fixes

* add DockerCart version query to admin auth CSS assets ([e88fdb6](https://github.com/mathflow-bit/dockercart/commit/e88fdb66b80deb0704da671f8f21c97f65255017))

## [1.31.0](https://github.com/mathflow-bit/dockercart/compare/v1.30.2...v1.31.0) (2026-05-29)

### Features

* add account dashboard, menu component and related UI updates ([9fdd9cb](https://github.com/mathflow-bit/dockercart/commit/9fdd9cb8a6d410c75454646869a042751e3b2496))
* add view‑full‑product link and href data attributes ([42f9cab](https://github.com/mathflow-bit/dockercart/commit/42f9cab0144e44958b968db734cc3d316ad675a8))
* full admin redesign, overhaul dashboard widgets, add DB indexes, ([834d958](https://github.com/mathflow-bit/dockercart/commit/834d95896537a39e02c6282a97bbcf29ff8b8cb4))
* improve order list UI and add datetime formatting ([4ecbb42](https://github.com/mathflow-bit/dockercart/commit/4ecbb42b3d1970fdc8f419d19cdf2d66a7c96ffd))

### Bug Fixes

* use datetime_format and clean up language strings ([d3ff619](https://github.com/mathflow-bit/dockercart/commit/d3ff6192b472bd55f688955dd5167b5bb84503ab))

## [1.30.2](https://github.com/mathflow-bit/dockercart/compare/v1.30.1...v1.30.2) (2026-05-28)

### Bug Fixes

* quickfix php error ([5ed89ed](https://github.com/mathflow-bit/dockercart/commit/5ed89eda592fc4d6210a70ac1214bd11c92f5ee5))

## [1.30.1](https://github.com/mathflow-bit/dockercart/compare/v1.30.0...v1.30.1) (2026-05-28)

### Bug Fixes

* quickfix ([935f8a3](https://github.com/mathflow-bit/dockercart/commit/935f8a38fef73c18e77637995a3b847e45a31366))

## [1.30.0](https://github.com/mathflow-bit/dockercart/compare/v1.29.3...v1.30.0) (2026-05-28)

### Features

* add background image field for categories ([ac627dd](https://github.com/mathflow-bit/dockercart/commit/ac627dd045df16bef3a66c2d417c13a7fca13343))
* add banner content position option ([106bc21](https://github.com/mathflow-bit/dockercart/commit/106bc211540b326d7e97804f1013185a26e254a8))
* add scheduled publication field to blog posts ([63ef59b](https://github.com/mathflow-bit/dockercart/commit/63ef59bb8dda6a21b1042c9d7831f25bde09ee2b))
* expose raw fields and enable inline editing in catalog lists ([8c9a974](https://github.com/mathflow-bit/dockercart/commit/8c9a97453fd6cc19dc07d004e79fb239e42772ec))
* redesign admin auth pages with custom layout and stylesheet ([f7e3e29](https://github.com/mathflow-bit/dockercart/commit/f7e3e29ff777784a7ee690b80c053ebf17d0531d))

## [1.29.3](https://github.com/mathflow-bit/dockercart/compare/v1.29.2...v1.29.3) (2026-05-27)

### Bug Fixes

* add discount percentage badge to product cards ([58090bc](https://github.com/mathflow-bit/dockercart/commit/58090bce8add9aac51d314e1f242c27411c94c04))
* expose call_for_price flag in module product arrays ([fdc460f](https://github.com/mathflow-bit/dockercart/commit/fdc460f9e46d3ef68f67167125afb02fc187cf93))
* guard latest module settings and add image fallback ([ae50510](https://github.com/mathflow-bit/dockercart/commit/ae505107f1890cee841b468e1b9bd038d3fc1b4e))
* include all descendant categories in search filters using recursive ([f3cad99](https://github.com/mathflow-bit/dockercart/commit/f3cad990285f3bdcaf45db48b673f3ce0a5f8750))
* sort filter attributes/options and respect currency decimal places ([8498543](https://github.com/mathflow-bit/dockercart/commit/849854363ca2409120f5035d1c4be2f8ddfcbc1d))

## [1.29.2](https://github.com/mathflow-bit/dockercart/compare/v1.29.1...v1.29.2) (2026-05-27)

### Bug Fixes

* add standard SEO rewrite rules to feed .htaccess generation ([41d71f5](https://github.com/mathflow-bit/dockercart/commit/41d71f5d8615f1b44ccf3a7bb1c332f65c87dbaa))
* convert product prices to default currency in dockercart filter ([e4f1b84](https://github.com/mathflow-bit/dockercart/commit/e4f1b84e94c62a82b7a982c101cd6f214a4ea857))

## [1.29.1](https://github.com/mathflow-bit/dockercart/compare/v1.29.0...v1.29.1) (2026-05-27)

### Bug Fixes

* multicurrency price conversion to filter module ([88ea4f1](https://github.com/mathflow-bit/dockercart/commit/88ea4f100149db8cd615ad6ca40ed90dcf4f0b12))
* update call‑for‑price translations and show button only for zero or ([d4ee3e0](https://github.com/mathflow-bit/dockercart/commit/d4ee3e0ccb6b626ca8ec6c83833817e26f452642))

## [1.29.0](https://github.com/mathflow-bit/dockercart/compare/v1.28.0...v1.29.0) (2026-05-27)

### Features

* add “You Save” display for discounts across UI ([4641879](https://github.com/mathflow-bit/dockercart/commit/46418796a4d57bc1b14da38f45e9a675b383f2b6))
* add auto_renew flag and auto‑renew logic for promotions ([b034b72](https://github.com/mathflow-bit/dockercart/commit/b034b724321a4b8bce30f5fd3b3f622f973a4665))
* add call‑for‑price support across admin and storefront ([5c723ba](https://github.com/mathflow-bit/dockercart/commit/5c723baf0215333b489ce9f2ad8c6e15e81ef32b))
* add currency conversion and customer‑group pricing to filter ([83276cd](https://github.com/mathflow-bit/dockercart/commit/83276cd2a0ca47aca15b51c887f8b6788dc1fc3f))
* drop popup image settings and add hybrid resize mode with ([a9ac995](https://github.com/mathflow-bit/dockercart/commit/a9ac995f97e86dc9133d0fbba83cbe04b1296d54))

### Bug Fixes

* add plural helper and integrate product count labels across ([71afbd0](https://github.com/mathflow-bit/dockercart/commit/71afbd04d44d97eab52cf3b6c0641d9cd1d29f7e))
* quickfix ([7382797](https://github.com/mathflow-bit/dockercart/commit/7382797f8cc8f6204ed4f2c37708aed8d2280b3b))
* translation ([9db86a6](https://github.com/mathflow-bit/dockercart/commit/9db86a667552f27bb5878fb53eeb2f6abadef046))
* update order date formatting to use datetime format ([dc9cb3f](https://github.com/mathflow-bit/dockercart/commit/dc9cb3f5894d6c55fa105ef55e97261761a29d30))

## [1.28.0](https://github.com/mathflow-bit/dockercart/compare/v1.27.8...v1.28.0) (2026-05-26)

### Features

* protect demo, social and test directories in file manager ([df3fb20](https://github.com/mathflow-bit/dockercart/commit/df3fb2088f4fe8e80dc05391f7d2000767467c98))
* sync .git/info/exclude with installed extensions ([03a508c](https://github.com/mathflow-bit/dockercart/commit/03a508c80541360d90da3a7193dc54480e8d72c3))

## [1.27.8](https://github.com/mathflow-bit/dockercart/compare/v1.27.7...v1.27.8) (2026-05-26)

### Bug Fixes

* add dashboard currency endpoint and cache‑aware refresh logic ([2c3bdd0](https://github.com/mathflow-bit/dockercart/commit/2c3bdd0d8b6dc853af17667590c30140b2708fff))
* prevent duplicate one‑click checkout scripts and modal injection ([61266ef](https://github.com/mathflow-bit/dockercart/commit/61266efb1cfa7fe2a98e9d3882d1115a7b5ca5f3))
* quickfix migration ([af55fea](https://github.com/mathflow-bit/dockercart/commit/af55feab74712a46b767f685dbe841379a370bc5))

## [1.27.7](https://github.com/mathflow-bit/dockercart/compare/v1.27.6...v1.27.7) (2026-05-26)

### Bug Fixes

* add conditional full‑width layout to information page ([9a2b041](https://github.com/mathflow-bit/dockercart/commit/9a2b04190bd631506c19168d49740eb6a0fb7a39))

## [1.27.6](https://github.com/mathflow-bit/dockercart/compare/v1.27.5...v1.27.6) (2026-05-26)

### Bug Fixes

* add multilingual section title and subtitle with backward ([412ac9a](https://github.com/mathflow-bit/dockercart/commit/412ac9ae6db07168d3e6af7fb6b2dc9db2d0711c))

## [1.27.5](https://github.com/mathflow-bit/dockercart/compare/v1.27.4...v1.27.5) (2026-05-26)

### Bug Fixes

* scope banner module CSS to instance ID to prevent global style ([758d53a](https://github.com/mathflow-bit/dockercart/commit/758d53abd8cb88e3871c7e32e94ae3c002487ea6))

## [1.27.4](https://github.com/mathflow-bit/dockercart/compare/v1.27.3...v1.27.4) (2026-05-26)

### Bug Fixes

* banner full-width layout CSS ([2c87942](https://github.com/mathflow-bit/dockercart/commit/2c879429e21457a137835ad64a244429e5ba56e8))

## [1.27.3](https://github.com/mathflow-bit/dockercart/compare/v1.27.2...v1.27.3) (2026-05-26)

### Bug Fixes

* banner width handling in theme ([66a4b98](https://github.com/mathflow-bit/dockercart/commit/66a4b98db25ed02afd4d1769e1ed15a65a2a7967))

## [1.27.2](https://github.com/mathflow-bit/dockercart/compare/v1.27.1...v1.27.2) (2026-05-26)

### Bug Fixes

* add full‑width option to banner module ([859fc8a](https://github.com/mathflow-bit/dockercart/commit/859fc8a8e0031df9aca0a00e59d2abc50f0ea90a))

## [1.27.1](https://github.com/mathflow-bit/dockercart/compare/v1.27.0...v1.27.1) (2026-05-26)

### Bug Fixes

* enhance banner video support and preview UI ([dc78a8d](https://github.com/mathflow-bit/dockercart/commit/dc78a8d45b987ab74b490933dfc626cf6fbc379a))

## [1.27.0](https://github.com/mathflow-bit/dockercart/compare/v1.26.0...v1.27.0) (2026-05-25)

### Features

* add section title and subtitle to shop features module ([af9ebc8](https://github.com/mathflow-bit/dockercart/commit/af9ebc870e3cf0f6e0a4b0397d13e7b0b78d1123))

### Bug Fixes

* enable file manager to show images and videos ([e0a1e17](https://github.com/mathflow-bit/dockercart/commit/e0a1e17d5fe2dceebf8d7cfa4fd0e934dba0c573))

## [1.26.0](https://github.com/mathflow-bit/dockercart/compare/v1.25.1...v1.26.0) (2026-05-25)

### Features

* add video support to file manager and banner ([babd7ab](https://github.com/mathflow-bit/dockercart/commit/babd7ab6762f9568a39dc8584b77a78e0cc4f762))
* expand Lucide icon picker options, add modal UI, and fix Russian ([70e91bb](https://github.com/mathflow-bit/dockercart/commit/70e91bbe17d8c0e64e1366b23ffe1e409533f451))

### Bug Fixes

* banner form CSS selector for image block ([18c01ff](https://github.com/mathflow-bit/dockercart/commit/18c01ff769f91ff473db136e3feb56c2174ca227))

## [1.25.1](https://github.com/mathflow-bit/dockercart/compare/v1.25.0...v1.25.1) (2026-05-25)

### Bug Fixes

* configure banner height ([70062b7](https://github.com/mathflow-bit/dockercart/commit/70062b7baf5b4443161c1254411f1779e36093e6))

## [1.25.0](https://github.com/mathflow-bit/dockercart/compare/v1.24.0...v1.25.0) (2026-05-25)

### Features

* add banner layout option to banner module ([c34c064](https://github.com/mathflow-bit/dockercart/commit/c34c06432d9144fc8e107298c79a3a03cd7e7aa7))
* add image optimisation on upload and config option ([2a2588e](https://github.com/mathflow-bit/dockercart/commit/2a2588ef2d0e97e8b71f2006e41744d5204efac5))

## [1.24.0](https://github.com/mathflow-bit/dockercart/compare/v1.23.0...v1.24.0) (2026-05-25)

### Features

* add video background support to banners ([12522a0](https://github.com/mathflow-bit/dockercart/commit/12522a0e1738a9bae0e81d1c6cfc13d123c1310a))
* replace header and footer link settings with multilingual JSON ([6ef436e](https://github.com/mathflow-bit/dockercart/commit/6ef436e091b9e8f18597aec1a95779a9b07e4b75))

## [1.23.0](https://github.com/mathflow-bit/dockercart/compare/v1.22.0...v1.23.0) (2026-05-23)

### Features

* add configurable header/footer links and UI enhancements ([283aad3](https://github.com/mathflow-bit/dockercart/commit/283aad3a5a409703a4500cea7ed2eda9edb96fc3))

### Bug Fixes

* update init.sql seed data and schema adjustments ([e87c0dd](https://github.com/mathflow-bit/dockercart/commit/e87c0ddc305d0e8b75f988540c07715f5fb72c19))

## [1.22.0](https://github.com/mathflow-bit/dockercart/compare/v1.21.3...v1.22.0) (2026-05-22)

### Features

* add Category Tree module with admin UI and front‑end display ([fff91ac](https://github.com/mathflow-bit/dockercart/commit/fff91ac5021fa043bf3c0f3b0198312d5bc09ea1))
* add configurable messenger icons and FAB widget ([49f2d88](https://github.com/mathflow-bit/dockercart/commit/49f2d886250f6e20073928adb76721fc0e15b1f0))
* add full‑width flag for information pages ([06ddbeb](https://github.com/mathflow-bit/dockercart/commit/06ddbeba4fbf80741bc4390304bd95fc72154719))
* add scroll-to-top button with animation and responsive styling ([413b621](https://github.com/mathflow-bit/dockercart/commit/413b621d6f99ac67456c4d8fb8ec173415be2e00))
* add selectable categories to Category module ([3982afd](https://github.com/mathflow-bit/dockercart/commit/3982afd55e12adca5cff835b99776ff424b2e87d))

### Bug Fixes

* add mobile categories accordion and enable scrolling in header menu ([766ae7d](https://github.com/mathflow-bit/dockercart/commit/766ae7d15bd327bf708287d325eec71c830646d6))
* adjust product page vertical padding ([6d28079](https://github.com/mathflow-bit/dockercart/commit/6d2807940031ed2b633b0a2c95c52fb752910497))
* cache get to return false when key is missing ([2b5678e](https://github.com/mathflow-bit/dockercart/commit/2b5678e825c8a743f58822f1c4f9dd2fe5abf53d))
* disable messenger FAB by default and fix status ([2aa51b2](https://github.com/mathflow-bit/dockercart/commit/2aa51b2e69b613afcd46d3eae0dbe50c4f68f8d8))
* display out‑of‑stock state for product option values ([0b6f6e8](https://github.com/mathflow-bit/dockercart/commit/0b6f6e8d7ab6316f5810f6f126064e2b295f9dbf))
* improve product price layout and responsiveness in product template ([58c48ad](https://github.com/mathflow-bit/dockercart/commit/58c48adffcba8c83c02d3f203809ff7eb58920ec))
* shorten wishlist and compare button labels and make product buttons ([1245e28](https://github.com/mathflow-bit/dockercart/commit/1245e2816317919ba92660c345e89a0bb7855ac2))
* update product bundle template for responsive layout and button ([42a2132](https://github.com/mathflow-bit/dockercart/commit/42a213294d9f2f1c1dd1392f7f91ceac7aa803e7))

## [1.21.3](https://github.com/mathflow-bit/dockercart/compare/v1.21.2...v1.21.3) (2026-05-21)

### Bug Fixes

* clean up stale YML import mappings when product missing ([fb681d8](https://github.com/mathflow-bit/dockercart/commit/fb681d8a01405579758cb8902143d65094096b64))

## [1.21.2](https://github.com/mathflow-bit/dockercart/compare/v1.21.1...v1.21.2) (2026-05-21)

### Bug Fixes

* missing endif in product search template ([0137207](https://github.com/mathflow-bit/dockercart/commit/013720754ed4c9a719ada24ca2c1189aee318b36))

## [1.21.1](https://github.com/mathflow-bit/dockercart/compare/v1.21.0...v1.21.1) (2026-05-21)

### Bug Fixes

* render FAQ answers with line breaks using nl2br ([dd70e49](https://github.com/mathflow-bit/dockercart/commit/dd70e492acbe4f6a7916b22f79e378347180da8d))

## [1.21.0](https://github.com/mathflow-bit/dockercart/compare/v1.20.1...v1.21.0) (2026-05-21)

### Features

* add FAQ caching and cache invalidation ([cd67f05](https://github.com/mathflow-bit/dockercart/commit/cd67f05f74506384a2f14d6b7294fd67fafaa3ef))

## [1.20.1](https://github.com/mathflow-bit/dockercart/compare/v1.20.0...v1.20.1) (2026-05-21)

### Bug Fixes

* enable media plugin in Tinymce ([57a5b69](https://github.com/mathflow-bit/dockercart/commit/57a5b69fe0e95df089042997bc89f74f86e467df))

## [1.20.0](https://github.com/mathflow-bit/dockercart/compare/v1.19.0...v1.20.0) (2026-05-21)

### Features

* add Redis cache support and configuration ([ce3bf39](https://github.com/mathflow-bit/dockercart/commit/ce3bf395d3668cbc43387e6678d002b7a7bb8e8b))

## [1.19.0](https://github.com/mathflow-bit/dockercart/compare/v1.18.1...v1.19.0) (2026-05-21)

### Features

* add product bundle feature with admin UI, DB schema, and cart ([b01b433](https://github.com/mathflow-bit/dockercart/commit/b01b4330356f9768d75deee0534fb15f498cb1aa))

### Bug Fixes

* allow overflow in product form gift tab table responsive div ([ea0dcbb](https://github.com/mathflow-bit/dockercart/commit/ea0dcbb300665094e12111ed243b14d20f808de5))
* improve one‑click checkout layout and product button flex styling ([577e149](https://github.com/mathflow-bit/dockercart/commit/577e149806a0a67da198ee2ecc8df1c843600c71))
* initialize bundles array in product controller ([0ed5269](https://github.com/mathflow-bit/dockercart/commit/0ed5269fef15062e987700c8403c02ed88559eff))
* tidy whitespace and change button margin class to mt-1 ([54085c9](https://github.com/mathflow-bit/dockercart/commit/54085c9da74cbd9181863494c2a3c8fc79290480))
* Ukrainian one‑click text, global phone mask ([7be8d36](https://github.com/mathflow-bit/dockercart/commit/7be8d369405e153f359e32619715f579016a61e8))

## [1.18.1](https://github.com/mathflow-bit/dockercart/compare/v1.18.0...v1.18.1) (2026-05-20)

### Bug Fixes

* rename one‑click button variable, add versioned asset URLs, and ([df90177](https://github.com/mathflow-bit/dockercart/commit/df901771b9a7fa4065897c1fba0efdad3f97424f))

## [1.18.0](https://github.com/mathflow-bit/dockercart/compare/v1.17.2...v1.18.0) (2026-05-20)

### Features

* integrate 1‑click checkout module into cart pages ([f4635d4](https://github.com/mathflow-bit/dockercart/commit/f4635d4749963bab80fb03125ee263325f44d335))

## [1.17.2](https://github.com/mathflow-bit/dockercart/compare/v1.17.1...v1.17.2) (2026-05-20)

### Bug Fixes

* persist product view mode with cookie and sync to localStorage ([eec4603](https://github.com/mathflow-bit/dockercart/commit/eec4603321e0ad5e6838ad35b37ab59fd6e0477b))

## [1.17.1](https://github.com/mathflow-bit/dockercart/compare/v1.17.0...v1.17.1) (2026-05-20)

### Bug Fixes

* view mode handling by removing redundant JS redirects and adding ([7dd056b](https://github.com/mathflow-bit/dockercart/commit/7dd056ba109bc973f0ff678a13b1f5ce539c9b57))

## [1.17.0](https://github.com/mathflow-bit/dockercart/compare/v1.16.0...v1.17.0) (2026-05-20)

### Features

* add grid/list/table view toggle with versioned assets and ([031dd15](https://github.com/mathflow-bit/dockercart/commit/031dd1500a938119a145fdb979343a73782ffb29))

## [1.16.0](https://github.com/mathflow-bit/dockercart/compare/v1.15.1...v1.16.0) (2026-05-20)

### Features

* add gift badge display to product modules ([a6df8a6](https://github.com/mathflow-bit/dockercart/commit/a6df8a60ce65a1ff0301a0e2ae68ee9829832398))
* add product gift with purchase feature ([15ed532](https://github.com/mathflow-bit/dockercart/commit/15ed532d13510fbcd0f86e8c720c6ee03791a051))
* add version query to assets cache busting ([d1fd23e](https://github.com/mathflow-bit/dockercart/commit/d1fd23e5900a5787b8e28a110e30565191cc4ad2))

### Bug Fixes

* prevent phone mask error when event key is undefined ([b169ae1](https://github.com/mathflow-bit/dockercart/commit/b169ae15e3b32f5e7946d80711eea116a6f9da4c))

## [1.15.1](https://github.com/mathflow-bit/dockercart/compare/v1.15.0...v1.15.1) (2026-05-20)

### Bug Fixes

* sort product option values by price then name ([185f13c](https://github.com/mathflow-bit/dockercart/commit/185f13c6e03d9297802690e1f987a8c4e7ef0878))

## [1.15.0](https://github.com/mathflow-bit/dockercart/compare/v1.14.1...v1.15.0) (2026-05-20)

### Features

* add fallback canonical URL generation in header ([53cd889](https://github.com/mathflow-bit/dockercart/commit/53cd889381dc5b874f4775a337ce8ad1db9700eb))
* remove controller SEO management UI and related endpoints ([64c82cb](https://github.com/mathflow-bit/dockercart/commit/64c82cbfd07e94a7a4c3048efc4c5a199c541ab5))
* use slash SEO URLs and redirect dash URLs ([2352484](https://github.com/mathflow-bit/dockercart/commit/2352484ba605ba82b4b20a0e541d166df68f5edc))

## [1.14.1](https://github.com/mathflow-bit/dockercart/compare/v1.14.0...v1.14.1) (2026-05-19)

### Bug Fixes

* align hero sections and simplify category header layout ([361adb6](https://github.com/mathflow-bit/dockercart/commit/361adb6d0c12a59c72b8f5f8d6ea5b62d10c7747))

## [1.14.0](https://github.com/mathflow-bit/dockercart/compare/v1.13.2...v1.14.0) (2026-05-19)

### Features

* manage module instances from extension list ([49c596c](https://github.com/mathflow-bit/dockercart/commit/49c596c7a3249b03057fb37b5e0387a5bb3f254d))

### Bug Fixes

* Add dc-view-all-link CSS class for widgets ([3408ee7](https://github.com/mathflow-bit/dockercart/commit/3408ee7c4a08b0b631a34140c1cf66454d600f5f))

## [1.13.2](https://github.com/mathflow-bit/dockercart/compare/v1.13.1...v1.13.2) (2026-05-19)

### Bug Fixes

* fallback phone mask when country field hidden ([93c1b28](https://github.com/mathflow-bit/dockercart/commit/93c1b28172a327a4bfc19ea5176b3242a2aa4a83))

## [1.13.1](https://github.com/mathflow-bit/dockercart/compare/v1.13.0...v1.13.1) (2026-05-18)

### Bug Fixes

* silence deprecation warnings in error handlers ([a4e3759](https://github.com/mathflow-bit/dockercart/commit/a4e375970475cab9222efaccbc81dae6a86fb083))

## [1.13.0](https://github.com/mathflow-bit/dockercart/compare/v1.12.4...v1.13.0) (2026-05-18)

### Features

* auto-contrast badge text from accent color ([ae3454d](https://github.com/mathflow-bit/dockercart/commit/ae3454d07dd0baf14b4432ac8679691d6a71f6ae))

## [1.12.4](https://github.com/mathflow-bit/dockercart/compare/v1.12.3...v1.12.4) (2026-05-18)

### Bug Fixes

* apply accent background to banner badges ([e649339](https://github.com/mathflow-bit/dockercart/commit/e649339588f65d7e0de8e9863d2b1608c2876021))

## [1.12.3](https://github.com/mathflow-bit/dockercart/compare/v1.12.2...v1.12.3) (2026-05-15)

### Bug Fixes

* tighten error logging and remove debug logs ([05ab6af](https://github.com/mathflow-bit/dockercart/commit/05ab6af0134f4887bf14c8d975687a3b4bd2545a))

## [1.12.2](https://github.com/mathflow-bit/dockercart/compare/v1.12.1...v1.12.2) (2026-05-15)

### Bug Fixes

* suppress low-severity PHP errors from the log ([406b67f](https://github.com/mathflow-bit/dockercart/commit/406b67f42f3404b319df52ef1382c7815cb4fd33))

## [1.12.1](https://github.com/mathflow-bit/dockercart/compare/v1.12.0...v1.12.1) (2026-05-12)

### Bug Fixes

* remove WeChat Pay payment extension ([a5e1ed8](https://github.com/mathflow-bit/dockercart/commit/a5e1ed8ab0fa80003728cb4f1b60c87d452031be))

## [1.12.0](https://github.com/mathflow-bit/dockercart/compare/v1.11.0...v1.12.0) (2026-05-12)

### Features

* add Beta label to NovaPost heading ([9028cb1](https://github.com/mathflow-bit/dockercart/commit/9028cb1ca6018cdd1e60fc76a8b783e523b6f2f2))
* add lock files and runtime dependency install ([b4ada96](https://github.com/mathflow-bit/dockercart/commit/b4ada96e93a8403181f97a091d0eb0ba888022b2))
* add multilingual NovaPost division support ([5423138](https://github.com/mathflow-bit/dockercart/commit/5423138b92348060f7083986b1f9cfa07d8f9a2f))
* Add NovaPost city autocomplete and delivery types ([d806e36](https://github.com/mathflow-bit/dockercart/commit/d806e362212534e3baa25b6a6ebee58e35e40649))
* add NovaPost shipping module ([b188106](https://github.com/mathflow-bit/dockercart/commit/b1881060a1e166c6a79273ed621b4acda9f7d501))
* add UAH rate and adjust price formatting ([13828f8](https://github.com/mathflow-bit/dockercart/commit/13828f8f91652590a92007b379dedebffba3be29))
* convert gallery list to card grid ([83cfe4b](https://github.com/mathflow-bit/dockercart/commit/83cfe4be78bbbbdd800a2a2a9d86b9258439fccf))
* detect .local domains as local development environment ([efd74f8](https://github.com/mathflow-bit/dockercart/commit/efd74f81d365d49801662998b473c1f9f6b7ea06))
* remove OpenCart Marketplace integration ([15c06fc](https://github.com/mathflow-bit/dockercart/commit/15c06fca7065755c11452b1b38c791ef890c9ef5))

### Bug Fixes

* add heading title to product list and form ([b62b2ef](https://github.com/mathflow-bit/dockercart/commit/b62b2ef81944b9a43c0089f91a6fe713e9bfa853))
* add phone format fallback to one-click ([edc0ee5](https://github.com/mathflow-bit/dockercart/commit/edc0ee5cacf1adce1e8ab3af54b85b552fd36b0e))

## [1.11.0](https://github.com/mathflow-bit/dockercart/compare/v1.10.0...v1.11.0) (2026-05-09)

### Features

* add country phone format and enhance product filters ([3cad56b](https://github.com/mathflow-bit/dockercart/commit/3cad56bd75bc061fb07736a0dfa0093ebfd70dca))
* add customer group pricing to option values ([7dca882](https://github.com/mathflow-bit/dockercart/commit/7dca88270470403007fa5bd59d83a71984305946))

## [1.10.0](https://github.com/mathflow-bit/dockercart/compare/v1.9.1...v1.10.0) (2026-05-09)

### Features

* add DockerCart gallery module ([ee91d33](https://github.com/mathflow-bit/dockercart/commit/ee91d335e84b533ac810f9d3b832d3fedf881717))

### Bug Fixes

* use closest() for filemanager input value ([92cfc95](https://github.com/mathflow-bit/dockercart/commit/92cfc95033800e8b7ac73760a7925ee315d05eac))

## [1.9.1](https://github.com/mathflow-bit/dockercart/compare/v1.9.0...v1.9.1) (2026-05-08)

### Bug Fixes

* quickfix migration ([9eec479](https://github.com/mathflow-bit/dockercart/commit/9eec479af566526f4363a29511f7e5aee5b48c41))

## [1.9.0](https://github.com/mathflow-bit/dockercart/compare/v1.8.0...v1.9.0) (2026-05-08)

### Features

* Add brand carousel module ([acaf974](https://github.com/mathflow-bit/dockercart/commit/acaf97432dddc47cc921ab3bc295a55e7e36f2a8))

## [1.8.0](https://github.com/mathflow-bit/dockercart/compare/v1.7.3...v1.8.0) (2026-05-07)

### Features

* add inline product image editing ([691d41b](https://github.com/mathflow-bit/dockercart/commit/691d41bd015c6c49e139f3c0f8a368658b9e6537))

## [1.7.3](https://github.com/mathflow-bit/dockercart/compare/v1.7.2...v1.7.3) (2026-05-07)

### Bug Fixes

* add debug ([d4dada2](https://github.com/mathflow-bit/dockercart/commit/d4dada29c62ec7c12ed97bf5663d7b17fd0b28aa))

## [1.7.2](https://github.com/mathflow-bit/dockercart/compare/v1.7.1...v1.7.2) (2026-05-07)

### Bug Fixes

* tune timeouts and improve YML import ([1a2680d](https://github.com/mathflow-bit/dockercart/commit/1a2680d682638196f2fa99ce741f3d44b10791b3))

## [1.7.1](https://github.com/mathflow-bit/dockercart/compare/v1.7.0...v1.7.1) (2026-05-07)

### Bug Fixes

* retry YML fetch, fix UA text typo ([1ee80cc](https://github.com/mathflow-bit/dockercart/commit/1ee80cc28483045df1047ac9cee9d831919f95cd))

## [1.7.0](https://github.com/mathflow-bit/dockercart/compare/v1.6.1...v1.7.0) (2026-05-07)

### Features

* add currency tag to CG price mapping ([9050a35](https://github.com/mathflow-bit/dockercart/commit/9050a359763f859b967034574cfa38ca3ac94823))

## [1.6.1](https://github.com/mathflow-bit/dockercart/compare/v1.6.0...v1.6.1) (2026-05-07)

### Bug Fixes

* Add main price tag to YML import profile ([6a5c7e4](https://github.com/mathflow-bit/dockercart/commit/6a5c7e4611600db7420387a56979f797a187541d))

## [1.6.0](https://github.com/mathflow-bit/dockercart/compare/v1.5.0...v1.6.0) (2026-05-07)

### Features

* add customer group price mapping to YML import ([ac5f56d](https://github.com/mathflow-bit/dockercart/commit/ac5f56d27db857162e19eb61e36faff435d422a2))
* add per-product customer group prices ([f2a65ac](https://github.com/mathflow-bit/dockercart/commit/f2a65ac748823dcec9c1f0822502c7c85207f93a))

## [1.5.0](https://github.com/mathflow-bit/dockercart/compare/v1.4.12...v1.5.0) (2026-05-06)

### Features

* handle duplicate SEO keywords for categories ([7064ce9](https://github.com/mathflow-bit/dockercart/commit/7064ce97c50aded200253d7fc71c094dc42a7e3d))

### Bug Fixes

* scope SEO URL duplicate check to current language ([b0e776b](https://github.com/mathflow-bit/dockercart/commit/b0e776bd552313c3ddf7e619ac7d059a59f07ca9))

## [1.4.12](https://github.com/mathflow-bit/dockercart/compare/v1.4.11...v1.4.12) (2026-05-05)

### Bug Fixes

* add redirect creation in Journal blog migration ([1561e3b](https://github.com/mathflow-bit/dockercart/commit/1561e3bea1a2fff1b5e734e738545ded1d9ec87a))

## [1.4.11](https://github.com/mathflow-bit/dockercart/compare/v1.4.10...v1.4.11) (2026-05-05)

### Bug Fixes

* group articles by alternate links ([12209a0](https://github.com/mathflow-bit/dockercart/commit/12209a02bb09d86c8399c5aa87e53d13a7197e73))

## [1.4.10](https://github.com/mathflow-bit/dockercart/compare/v1.4.9...v1.4.10) (2026-05-05)

### Bug Fixes

* Scope SEO URL checks by language ([6906f1e](https://github.com/mathflow-bit/dockercart/commit/6906f1eec758a51614c28eed049d52b1205656c6))

## [1.4.9](https://github.com/mathflow-bit/dockercart/compare/v1.4.8...v1.4.9) (2026-05-05)

### Bug Fixes

* preserve blog category SEO URLs during migration ([197295c](https://github.com/mathflow-bit/dockercart/commit/197295c5330008aa2f4e3bf434b926fa9d7527c1))
* remove trailing dots from blog cache keys ([80efd91](https://github.com/mathflow-bit/dockercart/commit/80efd919d14bd03fd1a43a1ca5d70cfb36d0ffd6))

## [1.4.8](https://github.com/mathflow-bit/dockercart/compare/v1.4.7...v1.4.8) (2026-05-05)

### Bug Fixes

* aggregate categories across language versions ([7265307](https://github.com/mathflow-bit/dockercart/commit/72653077727d8337c3c2105d93626d4e1c2f5a56))

## [1.4.7](https://github.com/mathflow-bit/dockercart/compare/v1.4.6...v1.4.7) (2026-05-05)

### Bug Fixes

* group articles by hreflang alternate links ([669e726](https://github.com/mathflow-bit/dockercart/commit/669e726310bdb7a8637e1802727a81bc6e9fc3a4))

## [1.4.6](https://github.com/mathflow-bit/dockercart/compare/v1.4.5...v1.4.6) (2026-05-05)

### Bug Fixes

* cascade blog cache invalidation across entities ([c175cc0](https://github.com/mathflow-bit/dockercart/commit/c175cc08078d1f2d0b357aff80bd300d5ca4cb72))

## [1.4.5](https://github.com/mathflow-bit/dockercart/compare/v1.4.4...v1.4.5) (2026-05-05)

### Bug Fixes

* add pagination and sorting to blog admin lists ([286859a](https://github.com/mathflow-bit/dockercart/commit/286859a49392ef43db1ff933e9d67e1259d4b488))

## [1.4.4](https://github.com/mathflow-bit/dockercart/compare/v1.4.3...v1.4.4) (2026-05-05)

## [1.4.3](https://github.com/mathflow-bit/dockercart/compare/v1.4.2...v1.4.3) (2026-05-05)

### Bug Fixes

* prevent appending port 80/443 behind proxy ([2525c15](https://github.com/mathflow-bit/dockercart/commit/2525c1512a3cae293e60f18fb8650a637962c887))

## [1.4.2](https://github.com/mathflow-bit/dockercart/compare/v1.4.1...v1.4.2) (2026-05-05)

## [1.4.1](https://github.com/mathflow-bit/dockercart/compare/v1.4.0...v1.4.1) (2026-05-04)

## [1.4.0](https://github.com/mathflow-bit/dockercart/compare/v1.3.3...v1.4.0) (2026-05-04)

### Features

* add Journal blog migration tool ([803bbe8](https://github.com/mathflow-bit/dockercart/commit/803bbe8e498a95ba7bc2a929b354b223670ef85e))

## [1.3.3](https://github.com/mathflow-bit/dockercart/compare/v1.3.2...v1.3.3) (2026-05-04)

## [1.3.2](https://github.com/mathflow-bit/dockercart/compare/v1.3.1...v1.3.2) (2026-05-03)

### Bug Fixes

* increase nginx file descriptor limits ([c4120eb](https://github.com/mathflow-bit/dockercart/commit/c4120eb4f8b6a005d9a657c4fe773e7dcee1a0d7))

## [1.3.1](https://github.com/mathflow-bit/dockercart/compare/v1.3.0...v1.3.1) (2026-05-03)

### Bug Fixes

* remove duplicate address fields from checkout form ([7ac0c4d](https://github.com/mathflow-bit/dockercart/commit/7ac0c4da42a12e026c58e92909674443bacc3142))

## [1.3.0](https://github.com/mathflow-bit/dockercart/compare/v1.2.0...v1.3.0) (2026-05-03)

### Features

* Add address field reordering based on country format ([6d60855](https://github.com/mathflow-bit/dockercart/commit/6d6085512152814609c9636d4807da17a5bbdced))
* add default country and zone settings to checkout module ([5db5493](https://github.com/mathflow-bit/dockercart/commit/5db5493ab6a00dbedb5ed100f418fe1ae91b6b6e))
* add default country/zone settings to checkout ([472749a](https://github.com/mathflow-bit/dockercart/commit/472749a2b74cbc7587572a90213ebea033049942))
* add test customer Mykyta to init.sql ([9329e1e](https://github.com/mathflow-bit/dockercart/commit/9329e1e857d2b0c78533e47c866a4137bb9098b4))
* make extension cards clickable to edit ([679915e](https://github.com/mathflow-bit/dockercart/commit/679915e0b423a295fd5b52174043678e47828224))

### Bug Fixes

* double slash in language-prefixed SEO redirect URLs ([35b0adf](https://github.com/mathflow-bit/dockercart/commit/35b0adfb2e0069fa209117141a84da4ce9d10320))
* persist extension type filter across page reloads ([fa62318](https://github.com/mathflow-bit/dockercart/commit/fa62318c59cbfece8db32d227aa0f16e73022ffc))
* preserve non-standard port in SEO URL redirects ([1f79fd4](https://github.com/mathflow-bit/dockercart/commit/1f79fd4f044d39b44a8a68270ba495bdc75c1b92))
* preserve non-standard port in SEO URL redirects ([5bb73a5](https://github.com/mathflow-bit/dockercart/commit/5bb73a58af2a2c149fa34221c28f5065a531dad8))
* preserve port in generated URLs ([2358453](https://github.com/mathflow-bit/dockercart/commit/23584539db4a40a2cdd7552192b5e415d29ac73f))
* selinux resolve problems with rights ([3d7508d](https://github.com/mathflow-bit/dockercart/commit/3d7508d30e7a458a3da7dff80d9854b5189bc64d))
* toggle grid row visibility and handle new address defaults in ([baf669f](https://github.com/mathflow-bit/dockercart/commit/baf669f30006afa1f69e9011abf3ebb0ca6e519d))

## [1.2.0](https://github.com/mathflow-bit/dockercart/compare/v1.1.1...v1.2.0) (2026-04-27)

### Features

* add viewed products module ([71784e2](https://github.com/mathflow-bit/dockercart/commit/71784e2bec13f95f08b470bb14d85cfe2f4c51cb))

## [1.1.1](https://github.com/mathflow-bit/dockercart/compare/v1.1.0...v1.1.1) (2026-04-27)

## [1.1.0](https://github.com/mathflow-bit/dockercart/compare/v1.0.15...v1.1.0) (2026-04-27)

### Features

* add multiple of quantity for the products ([3a7ce89](https://github.com/mathflow-bit/dockercart/commit/3a7ce891494697896f56f79aebe09b5193fe859b))

## [1.0.15](https://github.com/mathflow-bit/dockercart/compare/v1.0.14...v1.0.15) (2026-04-26)

### Bug Fixes

* update.sh ([fe8c7d4](https://github.com/mathflow-bit/dockercart/commit/fe8c7d4abe32fe2eff2197ae628e78c2ee6aaacd))

## [1.0.14](https://github.com/mathflow-bit/dockercart/compare/v1.0.13...v1.0.14) (2026-04-26)

### Bug Fixes

* bug with version in docker container inodes ([4e8ac3a](https://github.com/mathflow-bit/dockercart/commit/4e8ac3a752026547f1c8f2ecfd0437fc7eb082dc))

## [1.0.13](https://github.com/mathflow-bit/dockercart/compare/v1.0.12...v1.0.13) (2026-04-26)

## [1.0.12](https://github.com/mathflow-bit/dockercart/compare/v1.0.11...v1.0.12) (2026-04-26)

## [1.0.11](https://github.com/mathflow-bit/dockercart/compare/v1.0.10...v1.0.11) (2026-04-26)

## [1.0.10](https://github.com/mathflow-bit/dockercart/compare/v1.0.9...v1.0.10) (2026-04-26)

### Bug Fixes

* le ([2834ed5](https://github.com/mathflow-bit/dockercart/commit/2834ed55ae306cb997ea9595f703cb76c8092222))

## [1.0.9](https://github.com/mathflow-bit/dockercart/compare/v1.0.8...v1.0.9) (2026-04-26)

### Bug Fixes

* le fix ([93c4255](https://github.com/mathflow-bit/dockercart/commit/93c425517c8fb3d40c81e3808c287384ec54d0ee))

## [1.0.8](https://github.com/mathflow-bit/dockercart/compare/v1.0.7...v1.0.8) (2026-04-26)

### Bug Fixes

* lets encrypt renewal limits ([ac80e9a](https://github.com/mathflow-bit/dockercart/commit/ac80e9ac1935966e2a06240935d52ca0fdfb0ffc))

## [1.0.7](https://github.com/mathflow-bit/dockercart/compare/v1.0.6...v1.0.7) (2026-04-26)

### Bug Fixes

* version volumes ([4863d43](https://github.com/mathflow-bit/dockercart/commit/4863d43f56f214d0927d39ff58ae60a9c3e10d7a))

## [1.0.6](https://github.com/mathflow-bit/dockercart/compare/v1.0.5...v1.0.6) (2026-04-26)

### Bug Fixes

* yml import switch to http 1.1 ([ac4b53c](https://github.com/mathflow-bit/dockercart/commit/ac4b53c90280033682505599de08771c225e9b00))

## [1.0.5](https://github.com/mathflow-bit/dockercart/compare/v1.0.4...v1.0.5) (2026-04-26)

### Bug Fixes

* fix certbot autorestart container in standalone mode ([19f5684](https://github.com/mathflow-bit/dockercart/commit/19f5684e01fa6801983de5cf234bdffdeefc5562))

## [1.0.4](https://github.com/mathflow-bit/dockercart/compare/v1.0.3...v1.0.4) (2026-04-26)

### Bug Fixes

* add redirect from non-seo url to url with alias ([695f677](https://github.com/mathflow-bit/dockercart/commit/695f677d4cb8758412ab26dfd5ba5e00795f666a))

## [1.0.3](https://github.com/mathflow-bit/dockercart/compare/v1.0.2...v1.0.3) (2026-04-26)

## [1.0.2](https://github.com/mathflow-bit/dockercart/compare/v1.0.1...v1.0.2) (2026-04-26)

## [1.0.1](https://github.com/mathflow-bit/dockercart/compare/v1.0.0...v1.0.1) (2026-04-26)

### Bug Fixes

* Lint.yml php version leave only 8.4 ([e4a0409](https://github.com/mathflow-bit/dockercart/commit/e4a0409bcf4e80ec61d6eba7f86c56576836db65))

## 1.0.0 (2026-04-26)

### Bug Fixes

* correct vsftpd entrypoint script path (/usr/sbin/run-vsftpd.sh) ([cdeb52a](https://github.com/mathflow-bit/dockercart/commit/cdeb52a4ddb949759dd571248a0a1524d2449959))
* restore ftp service in docker-compose ([bad8c9c](https://github.com/mathflow-bit/dockercart/commit/bad8c9cd898300c8f8f56ece7ccc4a659c333c06))
* restore ftp service in docker-compose ([563b645](https://github.com/mathflow-bit/dockercart/commit/563b6459b05fc6030cf67c8ed3118202fa250389))

# Changelog

All notable changes to this project will be documented in this file.

DockerCart release automation is managed by `semantic-release`.
