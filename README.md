Nacatamal
=========

## What is Nacatamal?

Nacatamal is a deployment tool that I created to help me launch my code to production/development servers. I decided to 
create my own after trying Capistrano and other related tools. I found that there was too many options than what
I needed and they didn't work for me. So I created this to make it simple, quick and easy for me.

## Requirements

To use Nacatamal you have to have php version 5.5.9 and you can only use git as the VCS.

## Installation

In the root directory, simply run the following:
```
$ make install
```
This will download composer and install the required dependencies.

**---ADDITIONALLY---**
I have included php version 5.6.14 to be used with Nacatamal just in case no php is installed. 

Simply run the following:

```
$ make php install
```

## Getting Started

In order to use Nacatamal, create a config.yml and fill values like the distributed config.yml.

*   To package up your source code:

        php nacatamal package --project=myproject

*   To deploy the newest packaged code base to a server:

        php nacatamal deploy --project=myproject --build=latest --server=prod
        
## Other stuff I am considering

- be able to use this with Jenkins
- add some logging information for debug
- add a generator for the config file