[![CircleCI](https://circleci.com/gh/skluck/terraform-plan-parser.svg?style=shield)](https://circleci.com/gh/skluck/terraform-plan-parser)
[![Latest Stable Version](https://img.shields.io/packagist/v/skluck/terraform-plan-parser.svg?label=stable)](https://packagist.org/packages/skluck/terraform-plan-parser)
[![GitHub License](https://img.shields.io/github/license/skluck/terraform-plan-parser.svg)](https://packagist.org/packages/skluck/terraform-plan-parser)
![GitHub Language](https://img.shields.io/github/languages/top/skluck/terraform-plan-parser.svg)

# Terraform Plan Parser

This is a PHP library for parsing output from `terraform plan`.

It will attempt to parse out **changed attributes** of modified resources from `terraform plan` as well as **used modules** from `terraform init`.

## Table of Contents

- [Use Case](#use-case)
- [Installation](#installation)
- [Usage](#usage)

## Use Case

This library turns this:
```bash
Copying configuration from "git::https://git.example.com/terraform/module.git?ref=2.3.1"...

Initializing modules...
- module.fargate
  Getting source "./modules/fargate"

Initializing provider plugins...
- Checking for available provider plugins on https://releases.hashicorp.com...
- Downloading plugin for provider "aws" (2.2.0)...

An execution plan has been generated and is shown below.
Resource actions are indicated with the following symbols:
    ~ update in-place
-/+ destroy and then create replacement
    <= read (data resources)

Terraform will perform the following actions:

-/+ null_resource.promote_images (new resource required)
        id:                       "1236159896537553123" => <computed> (forces new resource)
        triggers.%:               "1" => "1"
        triggers.deploy_job_hash: "6c37ac7175bdf35e" => "1a0bd86fc5831ee6" (forces new resource)
```

into this:
```json
{
    "changedResources": [
        {
            "action": "replace",
            "name": "promote_images",
            "fully_qualified_name": "null_resource.promote_images",
            "module_path": "",
            "is_new": true,
            "is_tainted": false,
            "attributes": {
                "id": {
                    "name": "id",
                    "force_new_resource": true,
                    "old": {
                        "type": "string",
                        "value": "1236159896537553123"
                    },
                    "new": {
                        "type": "computed",
                        "value": null
                    }
                },
                "triggers.%": {
                    "name": "triggers.%",
                    "force_new_resource": false,
                    "old": {
                        "type": "string",
                        "value": "1"
                    },
                    "new": {
                        "type": "string",
                        "value": "1"
                    }
                },
                "triggers.deploy_job_hash": {
                    "name": "triggers.deploy_job_hash",
                    "force_new_resource": true,
                    "old": {
                        "type": "string",
                        "value": "6c37ac7175bdf35e"
                    },
                    "new": {
                        "type": "string",
                        "value": "1a0bd86fc5831ee6"
                    }
                }
            }
        }
    ],
    "modules": [
        {
            "name": "module.fargate",
            "source": "./modules/fargate",
            "version": null
        },
        {
            "name": "root",
            "source": "git::https://git.example.com/terraform/module.git?ref=2.3.1",
            "version": "2.3.1"
        }
    ]
}
```

This library is inspired and based on similar libraries for other languages. Check them out if PHP is not for you:
- [lifeomic/terraform-plan-parser](https://github.com/lifeomic/terraform-plan-parser) (typescript - cli)
- [chrislewisdev/prettyplan](https://github.com/chrislewisdev/prettyplan) (typescript - browser-based)

## Installation

Download this package with composer:
```
composer require skluck/terraform-plan-parser
```

## Usage

```php
<?php

use SK\TerraformParser\TerraformOutputParser;

$filename = '/path/to/terrraform/output';
$parser = new TerraformOutputParser;
$output = $parser->parseFile($filename);

var_export($output);
// [
//     'errors' => [
//         'Failed to parse resource name (line 63)',
//         'Failed to parse attribute name (line 102)',
//         'Failed to parse attribute name (line 103)',
//     ],
//     'changedResources' => [
//         ResourceChange,
//         ResourceChange,
//         ResourceChange,
//     ],
//     "modules": [
//         {
//             "name": "module.mymodule",
//             "source": "./path/to/submodule",
//             "version": null
//         },
//         {
//             "name": "root",
//             "source": "git::https://git.example.com/terraform/mymodule2.git?ref=2.3.1",
//             "version": "2.3.1"
//         }
//     ]
// ];
```

The output of this parser also implements `jsonSerialize` so it can be safely encoded and output with JSON.

```php
<?php

use SK\TerraformParser\TerraformOutputParser;

$filename = '/path/to/terrraform/output';
$contents = file_get_contents($filename);
$parser = new TerraformOutputParser;
$output = $parser->parse($contents);

echo json_encode($output, JSON_PRETTY_PRINT);
// {
//     "errors": [
//         "Failed to parse resource name (line 63)",
//         "Failed to parse attribute name (line 102)",
//         "Failed to parse attribute name (line 103)"
//     ],
//     "changedResources": [
//         ResourceChange,
//         ResourceChange,
//         {
//             "action": "create",
//             "name": "test2",
//             "fully_qualified_name": "module.test1.module.test2.null_resource.test2",
//             "module_path": "test1.test2",
//             "is_new": true,
//             "is_tainted": false,
//             "attributes": {
//                 "triggers.deploy_job_hash": {
//                     "name": "triggers.deploy_job_hash",
//                     "force_new_resource": true,
//                     "old": {
//                         "type": "string",
//                         "value": "6c37ac7175bdf35e"
//                     },
//                     "new": {
//                         "type": "computed",
//                         "value": null
//                     }
//                 }
//             }
//         }
//     ],
//     "modules": [
//         {
//             "name": "module.mymodule",
//             "source": "./path/to/submodule",
//             "version": null
//         },
//         {
//             "name": "root",
//             "source": "git::https://git.example.com/terraform/mymodule2.git?ref=2.3.1",
//             "version": "2.3.1"
//         }
//     ]
// }
```
