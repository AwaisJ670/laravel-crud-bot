![Laravel Crud Generator](https://banners.beyondco.de/Automated%20Crud%20Generation%20Tool.png?theme=dark&packageManager=composer+require&packageName=codebider%2Fgenerate-crud&pattern=zigZag&style=style_1&description=It+automate+the+process+of+repetitive+task+for+creating+crud.&md=1&showWatermark=0&fontSize=75px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)


This Laravel CRUD Generator package provides and generates Controller, Model (with eloquent relations), Migration, Routes and Views for developing your applications with a single command.
## Features
All these tasks will done under the proper folder structuring which is by default set to Admin and easily changed at the time of creation through --dir
- Will create **Migration** 
- Will create **Model**
- For Adding Eloquent relations in **Model** ask from developer
- Will create **Controller** or **Resource Controller** ask from developer
- Will create **views** 

## Requirements
    Laravel >= 8.x
    PHP >= 7.4

## Installation
1 - Install
```
composer require codebider/generate-crud
```
2- Publish the default package's config (optional)
```
php artisan vendor:publish --tag=crud-generator-config
```

## Usage
```
php artisan generate:crud --dir=

php artisan generate:crud --dir=admin
```


## Author

Awais Javaid // [Email Me](mailto:info.awaisjavaid@gmail.com)

Hire Me [LinkedIn](https://www.linkedin.com/in/malikawaisjavaid/)



