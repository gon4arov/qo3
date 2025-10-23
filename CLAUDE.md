# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an **OCMOD extension** for **ocStore 3.0.3.7** (OpenCart fork). The extension adds a customizable dropdown menu button to the admin panel header, allowing admins to create shortcuts to frequently accessed pages.

**Target platform**: ocStore 3.0.3.7
**Package format**: OCMOD (OpenCart Modification system)
**Extension name**: qmenu Admin Header Menu
**Primary language**: Russian/Ukrainian with English support

## Development Workflow

### Building the OCMOD Package

The extension is packaged as a `.ocmod.zip` file:

```bash
cd qo_admin_header_button_fixed.ocmod
zip -r ../qo_admin_header_button_fixed.ocmod.zip .
```

The OCMOD package must contain:
- `install.xml` - OCMOD manifest with file modification instructions
- `upload/` - directory with new files to be uploaded to the ocStore installation

### Testing Workflow

**IMPORTANT**: After each failed installation attempt, the hosting is rolled back to a clean ocStore 3.0.3.7 state. Only provide complete, working solutions - partial fixes are not acceptable.

To test the extension:
1. Create the `.ocmod.zip` package as shown above
2. Upload via admin panel: Extensions → Installer
3. Install the extension: Extensions → Extensions → Modules → qmenu → Install
4. Enable the module: Extensions → Extensions → Modules → qmenu → Edit → Status: Enabled
5. Refresh modifications: Extensions → Modifications → Refresh button

## Architecture

### OCMOD System

The extension uses OpenCart's XML-based modification system (`install.xml`) to:
- Inject PHP code into `admin/controller/common/header.php:34` (before `$data['stores'] = array();`)
- Modify the Twig template `admin/view/template/common/header.twig:11` (replacing `<ul class="nav navbar-nav navbar-right">`)
- Add CSS rules to `admin/view/stylesheet/stylesheet.css` (appended to end of file)

### Extension Components

**Controller**: `admin/controller/extension/module/qmenu.php`
- Handles module configuration UI
- Processes form submissions
- Provides autocomplete endpoints for route/category/product/information selection
- Sanitizes and validates menu items
- Manages default menu items (Store Settings, Refresh Modifications, Clear Mod Log, Error Log)

**View Template**: `admin/view/template/extension/module/qmenu.twig`
- Configuration interface with drag-and-drop sortable items
- jQuery UI sortable for reordering
- Color picker for menu item customization
- Autocomplete for routes and entities

**Language Files**:
- `admin/language/en-gb/extension/module/qmenu.php` (English)
- `admin/language/uk-ua/extension/module/qmenu.php` (Ukrainian)

**Assets**:
- `admin/view/stylesheet/qmenu.css` - Module-specific styles
- `admin/view/javascript/jquery/ui/jquery-ui.min.js` - jQuery UI for sortable/autocomplete

### Menu Item Types

The extension supports 5 types of menu items:

1. **link** - Custom URL (absolute or relative)
2. **route** - Admin route (e.g., `catalog/product`)
3. **category** - Direct link to edit a specific category
4. **product** - Direct link to edit a specific product
5. **information** - Direct link to edit a specific information page

### Data Storage

Settings are stored in `oc_setting` table:
- `module_qmenu_status` - Boolean (0/1)
- `module_qmenu_label` - String (button label in header)
- `module_qmenu_items` - JSON-encoded array of menu items

Each menu item has:
- `label` - Display text
- `type` - One of: link, route, category, product, information
- `href` - URL (for type=link)
- `route` - Admin route (for type=route)
- `category_id`, `category_name` - Category details
- `product_id`, `product_name` - Product details
- `information_id`, `information_name` - Information page details
- `color` - Hex color code (#RRGGBB or #RGB)
- `new_tab` - Boolean (0/1)
- `enabled` - Boolean (0/1)

### Header Injection Logic

The injected code in `admin/controller/common/header.php`:
- Loads language file for translations
- Builds menu data structure from settings
- Handles multilingual labels (extracts first non-empty value from arrays)
- Generates URLs with user_token for security
- Deduplicates items using `type::unique_target` keys
- Validates hex color codes with regex
- Falls back to default items if none configured

## Code Style & Patterns

- **PHP**: ocStore/OpenCart MVC pattern, snake_case for variables
- **JavaScript**: jQuery-based, camelCase for variables
- **Twig**: Standard Twig syntax with ocStore conventions
- **Language keys**: Prefix with module name context (e.g., `text_`, `entry_`, `column_`, `help_`, `error_`)

## Important Implementation Details

### Multi-language Handling

Labels are stored as strings but may arrive as arrays from the settings system. The code extracts the first non-empty value:

```php
if (is_array($label_setting)) {
    foreach ($label_setting as $value) {
        if (is_string($value) && trim($value) !== '') {
            $current_label = trim((string) $value);
            break;
        }
    }
}
```

### Deduplication

Items are deduplicated using a unique key based on type and target:
- `link::https://example.com`
- `route::catalog/product`
- `category::42`
- `product::123`
- `information::7`

### Security

- All admin routes include `user_token` from session
- Autocomplete endpoints validate `user_token`
- Color codes validated with regex: `~^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$~`
- User permissions checked via `$this->user->hasPermission('modify', 'extension/module/qmenu')`

### Default Items

When `module_qmenu_items` is null (first time), default items are created:
1. Store Settings (`setting/store`)
2. Refresh Modifications (`marketplace/modification/refresh`)
3. Clear Modification Log (`marketplace/modification/clearlog`)
4. Error Log (`tool/log`)

## File References

- OCMOD manifest: `qo_admin_header_button_fixed.ocmod/install.xml`
- Controller: `qo_admin_header_button_fixed.ocmod/upload/admin/controller/extension/module/qmenu.php`
- View: `qo_admin_header_button_fixed.ocmod/upload/admin/view/template/extension/module/qmenu.twig`
- Language EN: `qo_admin_header_button_fixed.ocmod/upload/admin/language/en-gb/extension/module/qmenu.php`
- Language UK: `qo_admin_header_button_fixed.ocmod/upload/admin/language/uk-ua/extension/module/qmenu.php`
