<?php

return [
    /**
     * Directory name where the generated models or controllers will reside.
     * For example, 'Admin' for administrative modules or 'FrontEnd' for user-facing modules.
     */
    'directory' => 'Admin', // e.g., Admin or FrontEnd

    /**
     * The operating system being used.
     * This value can help for previewing the migration file if needed.
     * It can be 'Windows', 'Linux', or 'MacOS'.
     */
    'OS' => 'Windows',

    /**
     * Columns to exclude from the 'fillable' array in the model.
     * These columns are typically auto-generated or managed by the database and 
     * do not require user input, hence they should not be included in the fillable list.
     */
    'columnsToRemove' => [
        'id',         // Primary key column, usually auto-incremented.
        'created_at', // Timestamp for when the record was created.
        'updated_at', // Timestamp for when the record was last updated.
        'deleted_at', // Timestamp for when the record was soft-deleted (if using soft deletes).
    ],
];
