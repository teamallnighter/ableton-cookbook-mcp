# Ableton Cookbook API - Usage Guide

## 🎯 Quick Start Checklist

### ✅ Setup (5 minutes)
1. **Import Collections:**
   - Import `Ableton-Cookbook-API.postman_collection.json`
   - Import `API-Tests.postman_collection.json`

2. **Import Environments:**
   - Import `Development.postman_environment.json`
   - Import `Production.postman_environment.json`
   - Select "Ableton Cookbook - Development" environment

3. **Test Connection:**
   - Run "Health Check" from API-Tests collection
   - Should return `200 OK` with `{"status": "ok"}`

### ✅ Authentication (2 minutes)
1. **Web Login (Easiest):**
   - Use "Login" request with your credentials
   - Session cookies will be set automatically

2. **API Token (For Apps):**
   - First login via web
   - Use "Create API Token" request
   - Token will be auto-saved to `auth_token` variable

## 🧪 Running Tests

### Full API Test Suite
```bash
# Run all tests via Newman (CLI)
newman run API-Tests.postman_collection.json -e Development.postman_environment.json
```

### Individual Test Groups
- **Setup Tests** - Health checks and connectivity
- **Authentication Tests** - Login validation
- **Rack API Tests** - CRUD operations
- **User API Tests** - Profile and social features  
- **Error Handling Tests** - Edge cases and errors

## 🔍 Common Use Cases

### 1. Browse Racks
```http
GET /api/v1/racks
```
- No authentication needed
- Returns paginated list of public racks
- Filter by type, category, user, tags

### 2. Upload New Rack
```http
POST /api/v1/racks
Content-Type: multipart/form-data
Authorization: Bearer {token}

title: "My Awesome Bass"
rack_file: [.adg file]
```
- Requires authentication
- File will be processed automatically
- Returns rack details with generated UUID

### 3. Follow Users
```http
POST /api/v1/users/{id}/follow
Authorization: Bearer {token}
```
- Requires authentication
- Enables social features
- Updates user counts automatically

### 4. Rate and Comment
```http
POST /api/v1/racks/{id}/rate
Authorization: Bearer {token}

{
  "rating": 5,
  "comment": "Amazing sound!"
}
```

## 📊 Response Examples

### Rack Response
```json
{
  "id": 188,
  "title": "Epic Bass Rack",
  "rack_type": "AudioEffectGroupDevice",
  "device_count": 11,
  "chain_count": 4,
  "average_rating": "4.30",
  "downloads_count": 120,
  "user": {
    "name": "Producer123"
  },
  "tags": ["bass", "808"]
}
```

### Paginated Response
```json
{
  "data": [...],
  "current_page": 1,
  "last_page": 188,
  "per_page": 20,
  "total": 3750,
  "links": [...],
  "meta": {...}
}
```

## 🚀 Advanced Features

### Filtering Examples
```http
# Bass instrument racks only
GET /api/v1/racks?filter[rack_type]=instrument&filter[category]=bass

# Recent uploads by user
GET /api/v1/users/123/racks?sort=-created_at&per_page=10

# Popular racks this week
GET /api/v1/racks?sort=-downloads_count&filter[created_after]=2025-08-13
```

### Bulk Operations
```http
# Get multiple racks by IDs
GET /api/v1/racks?filter[id]=1,2,3,4,5

# Batch rate multiple racks
POST /api/v1/racks/batch-rate
{
  "ratings": [
    {"rack_id": 1, "rating": 5},
    {"rack_id": 2, "rating": 4}
  ]
}
```

## 🔧 Customization

### Environment Variables
Customize these variables in your environment:

```json
{
  "base_url": "https://your-domain.com",
  "auth_token": "your-api-token",
  "default_per_page": "20",
  "api_timeout": "30000"
}
```

### Pre-request Scripts
Add custom logic before requests:

```javascript
// Auto-refresh expired tokens
if (pm.environment.get('token_expires') < Date.now()) {
  // Refresh logic here
}

// Add custom headers
pm.request.headers.add({
  key: 'X-App-Version',
  value: '1.0.0'
});
```

### Test Scripts
Enhance test validation:

```javascript
// Custom business logic tests
pm.test('Rack has valid Ableton version', function () {
  const rack = pm.response.json().data;
  pm.expect(rack.ableton_version).to.match(/^\d+\.\d+\.\d+$/);
});

// Save data for next request
pm.environment.set('last_rack_id', rack.id);
```

## 🐛 Troubleshooting Guide

### Authentication Issues
```
401 Unauthorized
```
**Solution:** Check auth token or login session

### Validation Errors
```
422 Unprocessable Entity
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```
**Solution:** Fix request body according to error details

### Rate Limiting
```
429 Too Many Requests
{
  "message": "Too Many Attempts."
}
```
**Solution:** Wait and retry, implement exponential backoff

### File Upload Issues
```
413 Payload Too Large
```
**Solution:** Check file size limits, compress if needed

## 📈 Performance Tips

### Optimize Requests
- Use `per_page` parameter to control response size
- Filter results to reduce data transfer
- Cache frequently accessed data locally

### Batch Operations
- Group related API calls together
- Use collection runners for bulk operations
- Implement proper error handling and retries

### Monitor Usage
- Check response headers for rate limit info
- Log API response times
- Monitor error rates and patterns

## 🔗 Integration Examples

### JavaScript/Node.js
```javascript
const response = await fetch(`${baseUrl}/api/v1/racks`, {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});
```

### Python
```python
import requests

headers = {
    'Authorization': f'Bearer {token}',
    'Accept': 'application/json'
}
response = requests.get(f'{base_url}/api/v1/racks', headers=headers)
```

### cURL
```bash
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     "$BASE_URL/api/v1/racks"
```

---

**Need Help?** 
- 📖 Check `/api/docs` for detailed API documentation
- 🐛 Report issues on GitHub  
- 📧 Contact support at admin@ableton.recipes

*Happy API testing!* 🎵