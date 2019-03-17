# Change Log

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](http://keepachangelog.com/).
> Sections: (`Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`)

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
