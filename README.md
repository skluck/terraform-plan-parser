[![CircleCI](https://circleci.com/gh/skluck/terraform-plan-parser.svg?style=shield)](https://circleci.com/gh/skluck/terraform-plan-parser)
[![Latest Stable Version](https://img.shields.io/packagist/v/skluck/terraform-plan-parser.svg?label=stable)](https://packagist.org/packages/skluck/terraform-plan-parser)
[![GitHub License](https://img.shields.io/github/license/skluck/terraform-plan-parser.svg)](https://packagist.org/packages/skluck/terraform-plan-parser)
![GitHub Language](https://img.shields.io/github/languages/top/skluck/terraform-plan-parser.svg)

# Terraform Plan Parser

This is a PHP library for parsing output from `terraform plan`.

It will attempt to parse out **changed attributes** of modified resources from `terraform plan` as well as **used modules** from `terraform init`.

It supports both Terraform 0.11 and Terraform 0.12:

- [`Terraform11OutputParser`](src/Terraform11OutputParser.php)
- [`Terraform12OutputParser`](src/Terraform12OutputParser.php)

Terraform 0.12 supports native JSON output of plan files, however it is not as complete as the stdout and so we must
continue to parse it.

## Table of Contents

- [Use Case](#use-case)
- [Installation](#installation)
- [Usage](#usage)
- [Data Structure](#data-structure)

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

This package requires PHP 7.1 or higher. The CI workflow tests against PHP 7.1, 7.2, and 7.3. It has no runtime
dependencies.

Download this package with composer:

```
composer require skluck/terraform-plan-parser ~1.1
```

## Usage

```php
<?php

use SK\TerraformParser\TerraformOutputParser;
use SK\TerraformParser\Terraform11OutputParser;
use SK\TerraformParser\Terraform12OutputParser;

$filename = '/path/to/terrraform/output';
$parser = new TerraformOutputParser;

// It is also possible to use the desired version of Terraform directly:
// $parser = new Terraform11OutputParser;
// $parser = new Terraform12OutputParser;

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

## Data Structure

The response of parsing terraform plan will always be an array of the following structure:
```
{
    "errors": array<string>
    "changedResources": array<ResourceChange>
    "modules": array<[name: string, source: string: version: ?string]>
}
```

The parser may successfully parse some resources, but still contain errors for others. So you should check both `errors`
and `changedResources` to determine your failure states. Generally I would recommend only failing if `errors` is
non-empty, and `changedResources` is empty, or if `errors` has some obscene number of messages.

**A Special Note about Modules:**  
This parser will attempt to find the "root" module from [Terragrunt](https://github.com/gruntwork-io/terragrunt) output.
It can be important information to know if the primary module is from a remote source (such as git or the
Terraform Registry) or a local directory.

### [`ResourceChange`](./src/Change/ResourceChange.php)

This represents individual resources in Terraform and has the following structure:

- `$resourceChange->action(): string`
  > The action being performed on the resource such as `create`, `destroy`, `replace`, `update`, or `read`.

- `$resourceChange->name(): string`
  > The name given to the resource in the Terraform code.

- `$resourceChange->type(): string`
  > The type of terraform resource such as [`aws_s3_bucket`](https://www.terraform.io/docs/providers/aws/r/s3_bucket.html).

- `$resourceChange->fullyQualifiedName(): string`
  > The full proper Terraform path to the resource, which will match the path in terraform state.

- `$resourceChange->modulePath(): string`
  > Path that includes only modules and does not include the resource type or name.
  > - Example Full Path: `module.mymodule1.module.mymodule2.aws_s3_bucket.mybucket`
  > - Module Path: `mymodule1.mymodule2`

- `$resourceChange->isTainted(): string`
  > If the resource was tainted, causing it to be recreated.

- `$resourceChange->isNew(): string`
  > If the resource is new (always false for Terraform 0.12).

- `$resourceChange->attributes(): array<AttributeChange>`
  > A list of attributes for this resource (see below).

### [`AttributeChange`](./src/Change/AttributeChange.php)

This represents individual attributes on resources. The parser will determine the type and if possible the old and
new values of this attribute.

- `$attrChange->name(): string`
  > The name of the attribute as determined by the Terraform resource (See your Terraform Provider documentation).

- `$attrChange->forceNewResource(): bool`
  > Whether this attribute is the cause of the resource being recreated.

- `$attrChange->oldValue(): ?array`
  > May be null, or an array of the `type` and `value`.  
  > For multiline blocks, maps, lists, and strings `value` is always null.
  >
  > Possible values for `type`:
  > - `unknown`
  > - `computed`
  > - `null`
  > - `number`
  > - `bool`
  > - `string`
  > - `list`
  > - `map`
  > - `block`

- `$attrChange->oldValue(): ?newValue`
  > See above.
