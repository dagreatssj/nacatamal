# Configuration files

## Description

The `projects.yml` file is required by Nacatamal in order to deploy and package projects. This file is
now generated, first you'll need to finish running `make install`. This will install the dependencies to run
the command `php nacatamal nacatamal:configure`. This will begin the process and ask you to identify a project.

## The Command

Run the following to get started with creating a project:

    php nacatamal nacatamal:configure

Once finished there will be a new file generated in `config` folder which will contain the parameters specifically
for the new project.

## Yaml Files

All projects are handled through this yaml file, `projects.yml`. During initialization another yaml file will be
created as well, `internals.yml`.

### projects.yml

This file contains the parameters specific for each project you would like to handle, please go to config/projects.yml
to view file after it completes generating. An overview of the what each parameters does is listed below:



### internals.yml

This file contains the default parameters created upon initialization of the first project. These parameters can be
changed at anytime.