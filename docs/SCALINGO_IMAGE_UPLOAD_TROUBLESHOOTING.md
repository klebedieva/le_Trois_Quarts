# Troubleshooting Image Upload Issues on Scalingo

## Problem: Images not showing in admin panel or disappearing

### Important Note about Scalingo File System

⚠️ **Scalingo uses an ephemeral file system** - files in the container are deleted when the container restarts. However, this should NOT affect images immediately after upload.

If images disappear immediately or don't show in admin panel, the issue is likely:
1. Files not being saved correctly
2. Path issues in database
3. EasyAdmin configuration conflicts

## Diagnostic Steps

### 1. Check Logs After Upload

After uploading an image, check Scalingo logs:

```bash
scalingo --app le-trois-quarts logs --lines 100
```

Look for:
- `Menu item image uploaded` - successful upload
- `Menu item image upload failed` - upload error
- Check the `upload_dir` path in logs

### 2. Verify Database Values

Check what's stored in the database:

```bash
scalingo --app le-trois-quarts run -- php bin/console dbal:run-sql "SELECT id, name, image FROM menu_item LIMIT 10"
```

Expected format: `image` should contain only filename like `plat-1-1234567890.jpg`, NOT full path.

### 3. Check File System (Temporary - for debugging)

```bash
# SSH into container
scalingo --app le-trois-quarts run bash

# Check if upload directory exists
ls -la public/uploads/menu/

# Check permissions
ls -ld public/uploads/menu/
```

### 4. Test Upload with Debugging

The code now includes logging. After uploading an image, check:
- Logs should show: `Menu item image uploaded` with details
- Database should have filename in `image` field
- File should exist in `public/uploads/menu/` (temporarily)

## Common Issues and Solutions

### Issue 1: Files Not Saving

**Symptoms:** No error, but files don't appear

**Possible causes:**
- Directory permissions
- Path resolution issues
- EasyAdmin overwriting values

**Solution:** Code now includes:
- Automatic directory creation
- Permission checks
- Protection against EasyAdmin overwriting

### Issue 2: Images Not Displaying in Admin

**Symptoms:** Files exist, but don't show in admin panel

**Possible causes:**
- Wrong path in database (full path instead of filename)
- BasePath configuration issue
- Web server not serving files from `/uploads/menu`

**Solution:** 
- Check database: `image` should be filename only
- Verify `setBasePath('/uploads/menu')` in ImageField
- Check web server configuration

### Issue 3: Images Disappear After Container Restart

**Symptoms:** Images work initially, disappear after restart

**Cause:** Scalingo's ephemeral file system

**Solution:** Use persistent storage:
- **Option A:** Use Scalingo Object Storage addon
- **Option B:** Use external storage (S3, etc.)
- **Option C:** Store images in database (not recommended for large files)

## Current Implementation

The code now:
1. ✅ Handles uploads manually (bypasses EasyAdmin issues)
2. ✅ Uses absolute paths via `kernel.project_dir`
3. ✅ Creates directory if missing
4. ✅ Checks permissions before upload
5. ✅ Validates file before saving
6. ✅ Stores only filename in database
7. ✅ Protects against EasyAdmin overwriting values
8. ✅ Logs upload success/failure

## Next Steps if Problem Persists

1. **Check logs** after upload attempt
2. **Verify database** - check what's stored in `image` field
3. **Test file system** - verify directory exists and is writable
4. **Check web server** - ensure `/uploads/menu/` is accessible

If images still don't work, consider:
- Using Scalingo Object Storage addon for persistent storage
- Implementing image storage in external service (S3, Cloudinary, etc.)

