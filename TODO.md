# Task: Link Stores to Categories and Configure Sidebar

## Plan:

1. **Create Store Model** (`app/Models/Store.php`)
   - [ ] Add relationship to User (belongsTo)
   - [ ] Add relationship to Category (belongsToMany - using category_store pivot table)

2. **Update User Model** (`app/Models/User.php`)
   - [ ] Add `stores()` relationship (hasMany)
   - [ ] Add `isSeller` attribute to check if user has a store

3. **Update Category Model** (`app/Models/Category.php`)
   - [ ] Add `stores()` relationship (belongsToMany)

4. **Create StoreResource** (`app/Filament/Resources/StoreResource.php`)
   - [ ] Create ListStores, CreateStore, EditStore, ViewStore pages
   - [ ] Create StoreForm schema with category selection (multi-select)
   - [ ] Create StoresTable configuration
   - [ ] Add validation: store is_active can only be true if user is_active is true

5. **Modify AdminPanelProvider** (`app/Providers/Filament/AdminPanelProvider.php`)
   - [ ] Add navigation ordering to place Stores after Users

6. **Modify UsersTable** (`app/Filament/Resources/Users/Tables/UsersTable.php`)
   - [ ] Add badge/indicator for users who have stores (sellers)
   - [ ] Add button to navigate to their store

## Followup steps:
- Run migrations if needed
- Clear cache: `php artisan optimize:clear`
- Test the functionality
