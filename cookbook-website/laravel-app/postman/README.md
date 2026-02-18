# Ableton Cookbook API - Postman Collection

This directory contains a comprehensive Postman collection for testing and exploring the Ableton Cookbook API.

## 📁 Files Included

- **`Ableton-Cookbook-API.postman_collection.json`** - Main API collection with all endpoints
- **`Development.postman_environment.json`** - Development environment variables
- **`Production.postman_environment.json`** - Production environment variables  
- **`README.md`** - This documentation file

## 🚀 Quick Start

### 1. Import Collection and Environment

1. Open Postman
2. Click **Import** button
3. Drag and drop or browse to import:
   - `Ableton-Cookbook-API.postman_collection.json`
   - `Development.postman_environment.json`
   - `Production.postman_environment.json`

### 2. Select Environment

Choose the appropriate environment from the dropdown in the top-right:
- **Ableton Cookbook - Development** for local testing (http://127.0.0.1:8080)
- **Ableton Cookbook - Production** for production API (https://ableton.recipes)

### 3. Authentication Setup

The API uses Laravel Sanctum for authentication. To get started:

1. **Using Web Authentication:**
   - Use the **Login** request in the Authentication folder
   - This will set session-based authentication automatically

2. **Using API Tokens:**
   - First login via the web interface or Login request
   - Use the **Create API Token** request to generate a bearer token
   - The token will be automatically saved to the `auth_token` environment variable

## 📚 API Endpoints Overview

### 🔐 Authentication
- `POST /login` - Login with email/password
- `POST /api/tokens` - Create API token

### 🎛️ Racks
- `GET /api/v1/racks` - List all racks (with filtering)
- `GET /api/v1/racks/{id}` - Get specific rack
- `POST /api/v1/racks` - Upload new rack
- `PUT /api/v1/racks/{id}` - Update rack
- `DELETE /api/v1/racks/{id}` - Delete rack
- `GET /api/v1/racks/{id}/download` - Download rack file

### 👥 Users
- `GET /api/v1/users/{id}` - Get user profile
- `GET /api/v1/users/{id}/racks` - Get user's racks
- `GET /api/v1/users/{id}/followers` - Get followers
- `GET /api/v1/users/{id}/following` - Get following
- `POST /api/v1/users/{id}/follow` - Follow user
- `DELETE /api/v1/users/{id}/unfollow` - Unfollow user
- `GET /api/v1/users/feed` - Get personal feed

### ⭐ Ratings & Comments
- `POST /api/v1/racks/{id}/rate` - Rate a rack
- `GET /api/v1/racks/{id}/comments` - Get comments
- `POST /api/v1/racks/{id}/comments` - Add comment

### 📚 Collections
- `GET /api/v1/collections` - Get user collections
- `POST /api/v1/racks/{id}/collect` - Add to collection

## 🔧 Environment Variables

The collection uses these variables that you can customize:

| Variable | Development | Production | Description |
|----------|-------------|------------|-------------|
| `base_url` | `http://127.0.0.1:8080` | `https://ableton.recipes` | API base URL |
| `auth_token` | (auto-set) | (auto-set) | Bearer token for authentication |
| `user_id` | `1` | (set manually) | Test user ID |
| `rack_id` | `1` | (set manually) | Test rack ID |
| `api_version` | `v1` | `v1` | API version |

## 🧪 Built-in Tests

Each request includes automated tests that check for:

- ✅ No 500 server errors
- ✅ Reasonable response times (< 5 seconds)
- ✅ Auto-extraction of auth tokens
- ✅ Response format validation

## 🔍 Filtering and Sorting

The **Get All Racks** request supports extensive filtering:

### Query Parameters
- `page` - Page number (default: 1)
- `per_page` - Items per page (max 100, default: 20)
- `sort` - Sort field (prefix with `-` for descending)

### Filtering Options
- `filter[rack_type]` - Filter by type (`instrument`, `audio_effect`, `midi_effect`)
- `filter[user_id]` - Filter by creator user ID
- `filter[category]` - Filter by category (e.g., `bass`, `lead`, `drums`)
- `filter[tags]` - Filter by tags

### Example: Get Latest Bass Instrument Racks
```
GET /api/v1/racks?filter[rack_type]=instrument&filter[category]=bass&sort=-created_at&per_page=10
```

## 🔒 Authentication Methods

The API supports two authentication methods:

### 1. Session Authentication (Web)
- Use cookies from web login
- Automatically handled by browser
- Good for web applications

### 2. Token Authentication (API)
- Use Bearer tokens in Authorization header
- Required for mobile apps and API clients
- Tokens don't expire but can be revoked

## 📝 Request Examples

### Upload a Rack
```http
POST /api/v1/racks
Content-Type: multipart/form-data
Authorization: Bearer {your-token}

title: "Epic Bass Rack"
description: "A powerful bass rack"
rack_type: "instrument"
category: "bass"
tags: "bass,808,trap"
rack_file: [binary .adg file]
image: [binary image file]
```

### Rate a Rack
```http
POST /api/v1/racks/1/rate
Content-Type: application/json
Authorization: Bearer {your-token}

{
  "rating": 5,
  "comment": "Amazing sound!"
}
```

## 🐛 Troubleshooting

### Common Issues

**401 Unauthorized**
- Make sure you're logged in or have a valid token
- Check that the `auth_token` environment variable is set

**422 Validation Error**
- Check request body format and required fields
- Ensure file uploads use `multipart/form-data`

**404 Not Found**
- Verify the endpoint URL is correct
- Check that resource IDs exist

**429 Too Many Requests**
- You've hit the rate limit
- Wait a few minutes before retrying

## 🔗 Related Resources

- **API Documentation:** Visit `/api/docs` on your API server for Swagger UI
- **OpenAPI Spec:** Available at `/api-docs.json`
- **GitHub:** [Ableton Cookbook Repository](https://github.com/teamallnighter/ableton-cookbook)

## 📞 Support

If you encounter issues with the API or Postman collection:

1. Check the troubleshooting section above
2. Review the API documentation at `/api/docs`
3. Create an issue on the GitHub repository
4. Contact support at admin@ableton.recipes

---

**Happy Testing!** 🎵

*Made with ❤️ for the Ableton community*