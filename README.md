
<h1>Laravel Custom Command for Automating CRUD Operations</h1>
<p>This library provides a custom Artisan command to automate the creation of database migrations, models, controllers, and views,routes in a Laravel application. The command allows you to interactively define fields for your database table, including their types, nullable status, and additional options for specific types like <code>decimal</code> and <code>boolean</code>.</p>

<h2>Features</h2>
<ul>
    <li><strong>Interactive CLI:</strong> Prompt-based input for creating migration fields.</li>
    <li><strong>Field Types:</strong> Supports various field types including <code>string</code>, <code>integer</code>, <code>decimal</code>, <code>boolean</code>, etc.</li>
    <li><strong>Special Handling:</strong></li>
    <ul>
        <li><strong>Decimal Fields:</strong> Prompts for precision and scale.</li>
        <li><strong>Boolean Fields:</strong> Prompts for a default value (true/false).</li>
    </ul>
    <li><strong>Automatic File Generation:</strong> Generates migration, model, controller,views and update the routes files in one go.</li>
    <li><strong>Automatic Migration:</strong> Runs the migration automatically after generating the migration file.</li>
</ul>

<h2>Installation</h2>
    <li><code>composer require hiqusol/generate-crud</code></li>
    <li><strong>Run the Command:</strong></li>
    <p>You can run the command using Artisan:</p>
    <pre><code>php artisan generate:crud</code></pre>
</ol>

<h2>Usage</h2>
<ol>
    <li><strong>Run the Command:</strong></li>
    <pre><code>php artisan generate:crud</code></pre>

    <li><strong>Follow the Prompts:</strong></li>
    <ul>
        <li><strong>Enter the model name.</strong></li>
        <li><strong>Define the fields interactively:</strong></li>
        <ul>
            <li><strong>Field Name:</strong> Enter the name of the field.</li>
            <li><strong>Field Type:</strong> Select field Type (e.g., <code>string</code>, <code>integer</code>, <code>decimal</code>, <code>boolean</code>).</li>
            <li><strong>Nullable:</strong> Specify if the field should be nullable (<code>Yes</code> or <code>No</code>).</li>
            <li><strong>Additional Options:</strong></li>
            <ul>
                <li>If the field type is <code>decimal</code>, you will be prompted for <code>precision</code> and <code>scale</code>.</li>
                <li>If the field type is <code>boolean</code>, you will be prompted for a default value (<code>1</code> for true, <code>0</code> for false).</li>
            </ul>
        </ul>
        <li>After defining all fields, the command will generate the necessary files</li>
        <li>It show the field name</li>
        <li>Ask for to Review the migration IF yes then open migration file in notepad on windows and on closing it It asks for migration file Is it correct on yes it run the migration</li>
        <li>After this it creating the migration file,model,Resource Controller</li>
        <li>Ask for Generate Views It only create file next work is done by yourself</li>
        <li>Ask for adding routes in web.php or api.php</li>
    </ul>

    <li><strong>Files Created:</strong></li>
    <ul>
        <li><strong>Migration File:</strong> Located in <code>database/migrations</code>.</li>
        <li><strong>Model File:</strong> Located in <code>app/Models</code>.</li>
        <li><strong>Controller File:</strong> Located in <code>app/Http/Controllers</code>.</li>
    </ul>
</ol>

<h2>Example</h2>
<p>Let's say you want to create a <code>Product</code> model with the following fields:</p>
<ul>
    <li><code>name</code>: <code>string</code>, nullable</li>
    <li><code>price</code>: <code>decimal</code>, precision <code>8</code>, scale <code>2</code></li>
    <li><code>is_active</code>: <code>boolean</code>, default <code>1</code></li>
</ul>
<p>You would run the command and provide the following inputs:</p>
<pre><code>php artisan generate:crud</code></pre>

<ul>
    <li>Model Name: <code>Product</code></li>
    <li>Table Name: <code>products</code></li>
    <li>Field Name: <code>name</code></li>
    <li>Field Type: <code>string</code></li>
    <li>Nullable: <code>Yes</code></li>
    <li>Field Name: <code>price</code></li>
    <li>Field Type: <code>decimal</code></li>
    <li>Precision: <code>8</code></li>
    <li>Scale: <code>2</code></li>
    <li>Nullable: <code>No</code></li>
    <li>Field Name: <code>is_active</code></li>
    <li>Field Type: <code>boolean</code></li>
    <li>Default Value: <code>1</code></li>
    <li>Nullable: <code>No</code></li>
</ul>
<p>This will generate the following:</p>
<ul>
    <li>Migration file with the specified fields.</li>
    <li><code>Product</code> model with <code>$table</code> and <code>$fillable</code> properties.</li>
    <li><code>ProductController</code> with basic CRUD methods.</li>
</ul>


