# PHP MCP Schema

[![Latest Version on Packagist](https://img.shields.io/packagist/v/php-mcp/schema.svg?style=flat-square)](https://packagist.org/packages/php-mcp/schema)
[![Total Downloads](https://img.shields.io/packagist/dt/php-mcp/schema.svg?style=flat-square)](https://packagist.org/packages/php-mcp/schema)
[![License](https://img.shields.io/packagist/l/php-mcp/schema.svg?style=flat-square)](LICENSE)

**Type-safe PHP DTOs for the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) specification.**

This package provides comprehensive Data Transfer Objects and Enums that ensure full compliance with the official MCP schema, enabling robust server and client implementations with complete type safety.

> 🎯 **MCP Schema Version:** [2025-03-26](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/main/schema/2025-03-26/schema.ts) (Latest)

## Installation

```bash
composer require php-mcp/schema
```

**Requirements:** PHP 8.1+ • No dependencies

## Quick Start

```php
use PhpMcp\Schema\Tool;
use PhpMcp\Schema\Resource;
use PhpMcp\Schema\Request\CallToolRequest;

// Create a tool definition
$tool = Tool::make(
    name: 'calculator',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'operation' => ['type' => 'string'],
            'a' => ['type' => 'number'],
            'b' => ['type' => 'number']
        ],
        'required' => ['operation', 'a', 'b']
    ],
    description: 'Performs basic arithmetic operations'
);

// Serialize to JSON
$json = json_encode($tool);

// Deserialize from array
$tool = Tool::fromArray($decodedData);
```

## Core Features

### 🏗️ **Complete Schema Coverage**
Every MCP protocol type is represented with full validation and type safety.

### 🔒 **Immutable Design**
All DTOs use readonly properties to prevent accidental mutations.

### 🚀 **Developer Experience**
- **Factory Methods:** Convenient `make()` methods for fluent object creation
- **Array Conversion:** Seamless `toArray()` and `fromArray()` methods
- **JSON Ready:** Built-in `JsonSerializable` interface support
- **Validation:** Comprehensive input validation with clear error messages

### 📦 **Schema Components**

| Component | Description |
|-----------|-------------|
| **Tools** | Tool definitions with JSON Schema validation |
| **Resources** | Static and template-based resource representations |
| **Prompts** | Interactive prompt definitions with arguments |
| **Content** | Text, image, audio, and blob content types |
| **JSON-RPC** | Complete JSON-RPC 2.0 protocol implementation |
| **Requests/Results** | All 15 request types and corresponding responses |
| **Notifications** | Real-time event notification messages |
| **Capabilities** | Client and server capability declarations |

## Usage Patterns

### Creating Protocol Messages

```php
// Initialize request
$request = InitializeRequest::make(
    protocolVersion: '2025-03-26',
    capabilities: ClientCapabilities::make(),
    clientInfo: Implementation::make('MyClient', '1.0.0')
);

// Call tool request  
$callRequest = CallToolRequest::make(
    name: 'calculator',
    arguments: ['operation' => 'add', 'a' => 5, 'b' => 3]
);
```

### Working with Resources

```php
// Static resource
$resource = Resource::make(
    uri: '/data/users.json',
    name: 'User Database',
    description: 'Complete user registry'
);

// Resource template
$template = ResourceTemplate::make(
    uriTemplate: '/users/{id}',
    name: 'User Profile',
    description: 'Individual user data'
);
```

### Content Handling

```php
// Text content
$text = TextContent::make('Hello, world!');

// Image content
$image = ImageContent::make(
    data: base64_encode($imageData),
    mimeType: 'image/png'
);
```

## Package Structure

```
src/
├── Content/         # Content types (Text, Image, Audio, Blob, etc.)
├── Enum/           # Protocol enums (LoggingLevel, Role)
├── JsonRpc/        # JSON-RPC 2.0 implementation
├── Notification/   # Event notification types
├── Request/        # Protocol request messages
├── Result/         # Protocol response messages
├── Tool.php        # Tool definitions
├── Resource.php    # Resource representations
├── Prompt.php      # Prompt definitions
└── ...            # Core protocol types
```

## Why Use This Package?

- ✅ **100% MCP Compliance** - Matches official specification exactly
- ✅ **Type Safety** - Catch errors at development time, not runtime  
- ✅ **Zero Dependencies** - Lightweight and self-contained
- ✅ **Production Ready** - Immutable, validated, and thoroughly tested
- ✅ **Future Proof** - Updated with latest MCP specification versions

## License

MIT License. See [LICENSE](LICENSE) for details.

---

**Part of the [PHP MCP](https://github.com/php-mcp) ecosystem** • Build type-safe MCP applications with confidence