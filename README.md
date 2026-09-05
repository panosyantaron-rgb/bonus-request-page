# Bonus Request Landing Page

A self-contained bonus request application with admin panel, persistent storage, and export functionality.

## Features

- **User Page** - Browse and submit bonus requests with username and ID
- **Admin Panel** - Manage available bonuses and view submitted requests
- **Persistent Storage** - All data saved to browser localStorage
- **Export Options** - Export requests as CSV or JSON
- **Dark Mode Support** - Automatic theme detection with CSS variables
- **Responsive Design** - Mobile-optimized interface

## Files

- `index.html` - HTML structure and page markup
- `style.css` - Complete styling with dark mode support
- `script.js` - JavaScript functionality and data management

## Admin Access

- Navigate to `/#/adminka` or access via link on user page
- Default password: `admin123` (change in script.js line 3)

## Storage Keys

- `bonuses_data` - Available bonuses list
- `bonus_requests` - Submitted bonus requests
- `admin_session` - Admin session state

## Local Development

1. Open `index.html` in a browser
2. For admin access, use the login page or navigate to `/#/adminka`
3. All data persists in your browser's localStorage

## Deployment

### Netlify (Recommended)

1. Push to GitHub
2. Connect repo to Netlify
3. Netlify auto-deploys on push

### Other Platforms

Simply upload the three files to any static hosting service.

## Browser Compatibility

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+

## License

MIT
