# pmmp-plugin-build-script

This repository provides a basic PHP script that lets you build your plugins for the Minecraft: Bedrock Edition server software [PocketMine-MP](https://github.com/pmmp/PocketMine-MP).
When executed, this script not only builds your plugin phar but also injects all [virions](https://github.com/poggit/support/blob/master/virion.md) that were used and defined in the plugin's `composer.json` file.

When running the script it will output the plugin's phar next to the `build-plugin.php` file.
As the name of the phar file the name of the parent directory is used. (In the example below: `YourPluginProject.phar`)

This script only supports virions of the specification version `3.0`, so they must be declared as one in their `composer.json` file.

This script can be used locally in your IDE or e.g. for automatic plugin phar building through GitHub Actions.

## How to use the script
The only file that you need from this repository is the `build-plugin.php` file.
To use it, copy and paste it into your plugin's folder next to e.g. the `src` folder and the `plugin.yml` file.
```
YourPluginProject
├── resources
    └── ...
├── src
    └── ...
├── composer.json (optionally)
├── plugin.yml
└── build-plugin.php
```

## Code used in this project
The code used in this project is modified code taken from the following projects:
- https://github.com/pmmp/PocketMine-MP/blob/stable/build/server-phar.php
- https://github.com/poggit/poggit/blob/000a86ff75481540b561a9bf6f363418012bdbdf/assets/php/virion.php#L147-L213
- https://github.com/SOF3/pharynx/blob/a42afb5e998769ea95ae5808ec25e459b2e3a6e6/src/Args.php#L322-L324