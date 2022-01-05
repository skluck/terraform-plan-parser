# Change Log

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](http://keepachangelog.com/).
> Sections: (`Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`)

## [1.3.1] - 2022-01-05

### Fixed
- Fixed parsing of unchanged blocks used in Terraform 0.13 and above:
  > Example output of a plan:
  > ```
  > -/+ resource "aws_sns_topic" "sns_topic" {
  >       ~ name   = "from this" ->  to this"
  >
  >       # (2 unchanged attributes hidden)
  >
  >       # (1 unchanged block hidden)
  > }
  > ```

## [1.3.0] - 2021-02-03

### Changed
- Added support for PHP 8.
- Updated unit tests to PHP Unit 9.

## [1.2.1] - 2020-04-14

### Fixed

- Terraform 0.12 Attributes containing a suffix of `# whitespace changes` will no longer raise errors.
  > Example output of a plan:
  > ```
  > -/+ resource "aws_sns_topic" "sns_topic" {
  >       ~ arn                                      = "arn:aws:sns:us-east-2:my-resource" -> (known after apply)
  >       ~ delivery_policy                          = jsonencode( # whitespace changes
  >             {
  >                 http = {
  >                     defaultHealthyRetryPolicy    = {
  >                         maxDelayTarget     = 20
  >                         numRetries         = 3
  >                     }
  >                 }
  >             }
  >         )
  >     }
  > ```

- Terraform 0.12 Resources containing `(deposed object XXXXXXXX)` will no longer raise errors.
  > Example output of a plan:
  > ```
  > # aws_alb_target_group.ecs[0] (deposed object 08221b78) will be destroyed
  > - resource "aws_alb_target_group" "ecs" {
  >     - arn    = "arn:aws:elasticloadbalancing:us-west-2:my-resource" -> null
  >     - name   = "ecs-myapp" -> null
  >   }
  > ```

## [1.2.0] - 2020-01-23

### Fixed

- Terraform 0.12 Attributes with empty blocks will no longer raise errors. They will be skipped and not reported
  since they do not represent a change.
  > Example output of a plan:
  > ```
  >   - resource "aws_lb_listener" "listener" {
  >       - arn = "example"
  >
  >       - timeouts {}
  >     }
  > ```

### Changed
- The version of a submodule will now be removed from module sources (This includes everything after `?ref=`).
- If a module subpath has been added **after** the `?ref=` version, it will now be removed from the version and appended
  to the source. This affects Terraform 0.11 only as this is invalid syntax in Terraform 0.12.

## [1.1.1] - 2020-01-02

### Fixed
- Terraform 0.12 resources that use `+/-` for replacement are now parsed correctly (In addition to `-/+`).
- Terraform 0.12 attribute blocks that use `# forces replacement` are now recognized properly.
- Data resources are now processed correctly in Terraform 0.12, which may sometimes have extra spaces before the brace.
- Resources that are `updated in-place` are now supported in addition to `updated`.

## [1.1.0] - 2019-12-31

### Added
- This package is now capable of parsing plan output from Terraform 0.12.
    - Parsing Terraform 0.12 has the following limitations:
        - Multiline blocks, maps, lists, and strings will not populate values.
        - `ResourceChange->isNew()` will always be false, but new resources can be simply detected with the `action`
          which will be `create`.
- Added `Terraform12OutputParser`.

### Changed
- Renamed `TerraformOutputParser` to `Terraform11OutputParser`.
- Updated `TerraformOutputParser` to attempt auto-detection of Terraform 0.11 or 0.12.
  > If you previously did not use the constructor arguments, there are no breaking changes for you.
  > The main parser will attempt to detect the correct version of Terraform by the presence of the
  > "resource comment line" (that begins with `#`). If no resources are changed, it will result in defaulting to
  > Terraform 0.11 mode.

## [1.0.0] - 2019-03-17

### Added
- Initial release of package
    - Added `TerraformOutputParser::parseFile($filename): array`
    - Added `TerraformOutputParser::parse($content): array`
    - Added `TerraformOutputParser::errors(): array`
    - Added `TerraformOutputParser::hasErrors(): bool`
    - Both parsing methods return an array with the following properties:
      > ```
      > [
      >     "errors" => [],
      >     "changedResources" => [],
      >     "modules" => [],
      > ]
      > ```
      > Descriptions:
      > - `errors`: a list of strings indicating errors
      > - `changedResources`: a list of `ResourceChange`
      > - `modules`: a list of modules used by the terraform run as well as their versions and source repository/directory.
