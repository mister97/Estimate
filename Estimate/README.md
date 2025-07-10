# FOSSBilling Estimate Module

A comprehensive estimate/quote management system for FOSSBilling that allows you to create professional estimates, send them to clients, and convert them to invoices seamlessly.

## Features

- **Complete Estimate Management**: Create, edit, view, and delete estimates with full lifecycle tracking
- **Professional PDF Generation**: Generate beautifully formatted PDF estimates with company branding
- **Client Portal Integration**: Clients can view, accept, or reject estimates through their portal
- **Email Integration**: Send and resend estimates using FOSSBilling's email template system
- **Invoice Conversion**: Convert accepted estimates to invoices with a single click
- **Status Tracking**: Track estimate status (draft, sent, accepted, rejected, converted, expired)
- **Permission System**: Granular permissions for staff members
- **Search & Filtering**: Find estimates by client, status, or estimate number
- **Tax Calculations**: Automatic tax calculations with configurable rates
- **Validity Periods**: Set expiration dates for estimates

## Installation

1. Download the module files
2. Extract to your FOSSBilling `modules/Estimate/` directory
3. Navigate to **Extensions → Modules** in your admin panel
4. Install and activate the Estimate module
5. Configure permissions for your staff members

### Dependencies

This module uses FOSSBilling's built-in Dompdf library for PDF generation. No additional installation required.

## Usage

### Admin Features

#### Creating Estimates
1. Go to **Estimates → Manage Estimates**
2. Click **Create New Estimate**
3. Select client and add estimate items
4. Set tax rate and validity period
5. Save as draft or send immediately

#### Managing Estimates
- **View**: See detailed estimate information
- **Edit**: Modify estimate details and items
- **Send**: Email estimate to client
- **Resend**: Send estimate again if needed
- **Accept**: Accept on behalf of client
- **Convert**: Convert to invoice
- **Download PDF**: Generate professional PDF

#### Status Management
- **Draft**: Estimate is being prepared
- **Sent**: Estimate has been emailed to client
- **Accepted**: Client has accepted the estimate
- **Rejected**: Client has rejected the estimate
- **Converted**: Estimate has been converted to invoice
- **Expired**: Estimate validity period has passed

### Client Features

#### Client Portal
Clients can access their estimates through the client portal:
- View all estimates with status
- Accept or reject estimates
- Download PDF copies
- Add rejection reasons

## Permissions

The module includes three permission levels:

- **Manage Estimates**: Create, edit, view, accept, and reject estimates
- **Send Estimates**: Send and resend estimates to clients
- **Convert Estimates**: Convert estimates to invoices

Configure these permissions in **Staff → Manage Staff → Edit Staff Member**.

## Email Templates

The module uses FOSSBilling's email template system with these templates:

- `mod_estimate_send`: Sent when estimate is emailed to client
- `mod_estimate_accepted`: Sent when estimate is accepted (optional notification)

You can customize these templates in **System → Email Templates**.

## API Endpoints

### Admin API
- `estimate_get_list`: Get list of estimates
- `estimate_get`: Get estimate details
- `estimate_create`: Create new estimate
- `estimate_update`: Update estimate
- `estimate_delete`: Delete estimate
- `estimate_send`: Send estimate to client
- `estimate_resend`: Resend estimate
- `estimate_accept`: Accept estimate (admin)
- `estimate_reject`: Reject estimate (admin)
- `estimate_convert_to_invoice`: Convert to invoice
- `estimate_pdf`: Generate PDF

### Client API
- `estimate_get_list`: Get client's estimates
- `estimate_get`: Get estimate details
- `estimate_accept`: Accept estimate
- `estimate_reject`: Reject estimate
- `estimate_pdf`: Download PDF

## Database Schema

The module creates two tables:

### `estimate`
- Complete estimate information
- Client relationship
- Status tracking
- Financial calculations
- Timestamps

### `estimate_item`
- Individual estimate line items
- Quantity, price, and totals
- Linked to parent estimate

## Configuration

### Company Information
Ensure your company details are configured in **System → Settings** for proper PDF generation:
- Company name and address
- Contact information
- Tax details

### Email Settings
Configure SMTP settings in **System → Email Settings** for estimate delivery.

## Workflow

1. **Create**: Admin creates estimate for client
2. **Send**: Estimate is emailed to client
3. **Review**: Client reviews estimate in portal
4. **Decision**: Client accepts or rejects
5. **Convert**: Accepted estimates become invoices
6. **Invoice**: Standard invoice workflow continues

## Support

For issues and feature requests:
- Submit issues on the GitHub repository
- Check FOSSBilling community forums
- Review documentation at [fossbilling.org](https://fossbilling.org)

## Technical Requirements

- FOSSBilling 0.6.0 or higher
- PHP 7.4 or higher
- MySQL/MariaDB database
- Composer (for PDF generation)

## Licensing

This extension is open source software and is released under the Apache v2.0 license. See [LICENSE](LICENSE) for the full license terms.

## Credits

Developed by KGC Innovations Ltd for the FOSSBilling community.

---

**Note**: This module integrates seamlessly with FOSSBilling's existing client, invoice, and email systems. No additional configuration is required beyond the initial setup.