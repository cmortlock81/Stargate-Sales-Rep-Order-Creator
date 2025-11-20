===========================================
Stargate Sales Rep Order Creator
===========================================
Version: 2.4.0
Author: Chris Mortlock
Description: A custom WooCommerce extension that allows sales reps to log in, 
create WooCommerce cash orders, and view their own sales dashboard. Designed 
for in-person ticket/cash sales using mobile devices.

Automatically creates:
- /rep-login
- /rep-dashboard
- /sell

===========================================
FEATURES
===========================================

✔ Sales Rep user role (locked down, no wp-admin access)  
✔ Auto-created front-end pages for login, selling, and dashboard  
✔ Login redirects reps to the dashboard (/rep-dashboard)  
✔ Rep Dashboard includes "← Sell Tickets" button  
✔ Order origin tagged as "mobile_sales_app" in WooCommerce  
✔ Reps can only access /rep-dashboard and /sell  
✔ Rep ID automatically attached to each order  
✔ WooCommerce order created with correct totals & status  
✔ Customer name/email collected at point of sale  
✔ Order status set to “processing”  
✔ Order notes include the rep ID  
✔ Fully mobile-friendly workflow  

===========================================
INSTALLATION
===========================================

1. Upload the plugin folder to:
   /wp-content/plugins/stargate-sales-rep-order-creator/

2. Activate the plugin in WordPress.

3. On activation, 3 required pages are automatically created:

   • /rep-login         – Sales rep login  
   • /rep-dashboard     – Sales rep order history  
   • /sell              – Sales rep ticket selling form  

4. Ensure the Sales Rep role exists with correct permissions:
   - read = true
   (This is already configured in v2.4.0)

5. Create users with the role:
   Sales Rep

6. Reps Log In At:
   https://yourdomain.com/rep-login

After login, reps are redirected to:
   https://yourdomain.com/rep-dashboard

7. They click the “← Sell Tickets” button to make new orders.

===========================================
USAGE: HOW REPS SELL TICKETS
===========================================

1. Sales rep visits /rep-login  
2. Logs in with assigned username/password  
3. Redirected to the /rep-dashboard  
4. Clicks the “← Sell Tickets” button  
5. Fills in:
   - Product  
   - Quantity  
   - Customer Name  
   - Customer Email  
6. Submits form  
7. WooCommerce order is automatically created:
   - Status: processing  
   - Payment: COD (cash)  
   - Origin: mobile_sales_app  
   - Rep ID stored in _sales_rep_id  

8. Customer receives WooCommerce emails automatically.

===========================================
DATA STORED
===========================================

Each order created by a sales rep includes:
- Order note (“Order created by rep: username”)  
- Meta field `_sales_rep_id` = rep username  
- Origin = mobile_sales_app  

This allows filtering, reporting, and analytics tracking.

===========================================
ROLE PERMISSIONS
===========================================

Sales Rep role includes ONLY:

read: true  
edit_posts: false  
delete_posts: false  
publish_posts: false  
upload_files: false  

They cannot:
- Access wp-admin  
- Edit posts  
- Upload media  
- Manage WooCommerce  
- Manage settings  

===========================================
SHORTCODES
===========================================

[sales_rep_login]  
Displays login form for sales reps.

[sales_rep_order_form]  
Displays the order creation form (/sell).

[sales_rep_dashboard]  
Displays the rep’s historical order list.

===========================================
TROUBLESHOOTING
===========================================

• Sales reps see a blank /sell page  
  → Ensure the “read” capability is set to TRUE.

• Plugin didn’t create pages  
  → Deactivate and reactivate the plugin.

• Reps accessing wp-admin  
  → Plugin forces redirect, so this should not happen.

• Orders show “origin unknown”  
  → Fixed in v2.4.0 (created_via = mobile_sales_app)

===========================================
END
===========================================
