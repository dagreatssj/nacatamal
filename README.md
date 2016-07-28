Nacatamal
=========

## What is Nacatamal?

Nacatamal is a deployment tool that I created to help me launch my code to production/development servers. I decided to 
create my own after trying Capistrano and other related tools. I found that there was too many options than what
I needed and they didn't work for me. So I created this to make it simple, quick and easy for me.

## Requirements

To use Nacatamal you have to have PHP (5.5.9)

## Installation

In the root directory, simply run the following:
```
$ make install
```
This will download composer and install the required dependencies.

## Getting Started

In order to use Nacatamal, edit the config.yml in the config folder.

*   To package up your source code:

        php nacatamal package --project=myproject

*   To deploy the newest packaged code base to a server:

        php nacatamal deploy --project=myproject --build=latest --server=prod
        
## Other stuff I am considering

- add some logging information for debug
- add a generator for the config file (similiar to Symfony's generators)