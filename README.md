# pmmp-plugin-build-script

This repository provides a basic PHP script that can be executed most ideally in the local IDE that lets you build your plugins for the Minecraft: Bedrock Edition server software [PocketMine-MP](https://github.com/pmmp/PocketMine-MP).
When executed, this script not only builds your plugin phar but also injects all [virions](https://poggit.github.io/support/virion.html) that were used and defined in the plugin's [.poggit.yml](https://poggit.github.io/support/virion.html) file.

When running the script it will output the plugin's phar next to the `build-plugin.php` file.
As the name of the phar file the name of the parent directory is used. (In the example below: `YourPluginProject.phar`) <br>

## How to use the script
The only file that you need from this repository is the `build-plugin.php` file.
To use it, copy and paste it into your plugin's folder next to e.g. the `src` folder and the `plugin.yml` file.
```
YourPluginProject
├── resources
    └── ...
├── src
    └── ...
├── .poggit.yml (optionally)
├── plugin.yml
└── build-plugin.php
```

There are two optional arguments, the script can be run with:
1. `php build-plugin.php --no-virions`
    When using the `--no-virions` argument the plugin phar will be built without any virions, regardless of if any were listed in the `.poggit.yml` file.
2. `php build-plugin.php --keep-virions`
    Normally, when the plugin phar is built, all virions that were downloaded and copied for injection. After this is done, those files are deleted.
    But when using the `--keep-virions` argument, those virion phars are not deleted and kept in a separate `virions` folder next to the `src` folder.

## Code used in this project
The code used in this project is modified code taken from the following projects:
- https://github.com/pmmp/PocketMine-MP/blob/stable/build/server-phar.php
- https://github.com/poggit/devirion/blob/master/cli.php
- https://stackoverflow.com/a/8971248/3990767