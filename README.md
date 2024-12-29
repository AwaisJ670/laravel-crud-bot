![Laravel Crud Generator](https://banners.beyondco.de/Automated%20Crud%20Generation%20Tool.png?theme=dark&packageManager=composer+require&packageName=codebider%2Fgenerate-crud&pattern=zigZag&style=style_1&description=It+automate+the+process+of+repetitive+task+for+creating+crud.&md=1&showWatermark=0&fontSize=75px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)


This Laravel CRUD Generator package provides and generates Controller, Model (with eloquent relations), Migration, Routes and Views for developing your applications with a single command.
## Features
1. Automatically generates files in the proper directory structure:
    - **Migration** 
    - **Model**
    - **Controller**
    - **Views**
    - **Routes**

2. Interactive prompts for input:
   - Asks for the **model name** (e.g., `Product`, `User`).
   - Collects **field names**, their data types, and whether they are nullable or not.
   - Allows you to add relationships in the model.

3. Provides options for:
   - Previewing the generated migration file before saving.
   - Editing the migration file directly in the system's editor specified in the configuration file.

4. Automatically propagates updates:
   - If you edit fields in the migration file, these changes will also be applied to dependent components like the `$fillable` property in models.

5. Controller creation:
   - Choose between generating a **basic controller** or a **resource controller**.

6. Route creation:
   - Choose to generate routes in either `web.php` or `api.php`.

7. View file generation:
   -  Choose to generate blade file or not.

## Requirements
    Laravel >= 8.x
    PHP >= 7.4

## Installation
1 - Install
```
composer require codebider/generate-crud
```
2- Publish the default package's config
```
php artisan vendor:publish --tag=crud-generator-config
```

## Usage with Workflow
This tool can create the files by typing the command
```
    php artisan generate:crud
``` 
1. **Model Name:**
   - Enter the desired model name (e.g., `Product`, `Category`). Follow Laravel's naming conventions (singular and CamelCase).
        ```
            Enter the model name:
        ```

2. **Field Names and Types:**
   - Input field names along with their data types and whether they are nullable. Use the following format:
     ```
        Enter field name (or press enter to stop adding fields):
     ```
     Example:
     ```
        Enter field name (or press enter to stop adding fields):
        > name
        Select field type by default (string):
        [0 ] string    
        [1 ] text      
        [2 ] longText  
        [3 ] integer   
        [4 ] unsignedIn
        [5 ] bigInteger
        [6 ] unsignedBi
        [7 ] json      
        [8 ] jsonb     
        [9 ] enum      
        [10] decimal   
        [11] float     
        [12] ipAddress 
        [13] boolean   
        [14] date      
        [15] datetime  
        [16] timestamp 
        > 0
        Nullable  [Yes]:
        [0] Yes
        [1] No
        > 0
     ```
   - Press **Enter** without typing anything to end the field input process.
        ```
            Enter field name (or press enter to stop adding fields):
            >
            Creating Migration File
            Updating Migration File
            fields are : name
        ```

3. **Preview Migration File:**
   - Once all fields are entered, preview the migration file to verify its contents.
        ```
            Do you want to review the migration file before proceeding? (yes/no) [yes]:
            > yes
            Opening migration file: G:\laragon\www\Expense\database\migrations/Api/2024_12_29_105049_create_products_table.php
        ```

4. **Edit Migration File:**
   - Preview migration in the specified operating system in the config file e.g(`config/crud_generator.php`).

5. **Propagating Changes:**
   - Any changes made to the migration file will automatically update other components where fields are required (e.g., `$fillable` in models).
        ```
            Is the migration file correct? (yes/no) [yes]:
            > yes
            Migration file is correct.
            Migrated Successfully.
            Creating Model
        ```

6. **Adding Relationships:**
   - Add relationships (e.g., one-to-one, one-to-many, many-to-many) to the model by following the interactive prompts.
        ```
            Do you want to add relationships to this model? [No]:
            [0] Yes
            [1] No
            > 0
        ```

7. **Creating a Controller:**
    - Choose to create a controller
        ```
            Do you want to generate Controller? [Yes]:
            [0] Yes
            [1] No
            >
        ```
   - Choose between a **basic controller** (minimal functionality) or a **resource controller** (includes all RESTful methods).
        ``` 
            Do you want to create a resource controller or a basic controller? [resource]:
            [0] resource
            [1] basic
            >
        ```
    - After Selection
        ```
            Resource controller created successfully at G:\laragon\www\Expense\app\Http\Controllers\Api\ProductController.php
        ```    

8. **Generating Routes:**
   - Select whether the routes should be added to `web.php` or `api.php` based on your application requirements.
        ```
            Where do you want to add routes? [web.php]:
            [0] api.php
            [1] web.php
            >
        ```
    - After Selection
        ```
            Generating web routes...
            Added route to G:\laragon\www\Expense\routes/web.php
        ```    

9. **Creating View Files:**
    - Choose to create a view file
        ```
            Do you want to generate views? [Yes]:
            [0] Yes
            [1] No
            >
        ```
     - After Selection
        ```
            Generating views...
            Created view file: G:\laragon\www\Expense\resources\views\Api\products/index.blade.php
        ```   

10. Successfully created the crud
    - After completion of all the steps, you will see a success message indicating that the CRUD operation
        ```
            Migration and cache cleared successfully!
            CRUD files for Product generated successfully!
            Log sent to server successfully.
        ```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like
to change.


## Author

Awais Javaid  [Email Me](mailto:info.awaisjavaid@gmail.com)

Hire Me [LinkedIn](https://www.linkedin.com/in/malikawaisjavaid/)




