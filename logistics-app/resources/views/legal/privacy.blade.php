@extends('legal.layout')
@section('title', 'Privacy Policy')

@section('content')
  <p>This Privacy Policy explains how the Logistics system (the "System") handles personal information, in line with the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

  <h2>1. Information we collect</h2>
  <ul>
    <li><strong>Account details:</strong> name, email address, role and profile photo.</li>
    <li><strong>Driver details:</strong> name, contact number and license information needed for dispatch.</li>
    <li><strong>Vehicle location:</strong> only when a driver chooses to share it during a delivery that is on the road, the phone's position about once a minute, while the delivery page is open. Sharing stops when the driver stops it, closes the page, or the delivery is finished. Each time sharing is started or stopped is recorded.</li>
    <li><strong>Proof of delivery:</strong> a photo and the name of the person who received the delivery.</li>
    <li><strong>Shipment details:</strong> sender and recipient names and addresses, and delivery records.</li>
    <li><strong>Activity records:</strong> sign-ins and actions taken in the System, with their date and time.</li>
  </ul>

  <h2>2. How we use it</h2>
  <p>To run deliveries, assign drivers and vehicles, show routes, keep the System secure, and keep records for auditing. We do not sell personal information or use it for advertising.</p>

  <h2>3. Third-party services</h2>
  <p>To show maps and routes, addresses and map coordinates are sent to OpenStreetMap services (map tiles, address lookup and the OSRM routing service). No account information is sent to them.</p>

  <h2>4. Who can see it</h2>
  <p>Only signed-in staff, limited by their role. Information is not shared outside the organization except where required by law.</p>

  <h2>5. Storage and security</h2>
  <p>Information is stored in the organization's database, protected by sign-in, role-based access and activity logging. Passwords are stored in hashed form, never as plain text.</p>

  <h2>6. How long we keep it</h2>
  <p>Records are kept for as long as needed for operations and auditing, then deleted or anonymized. Vehicle location data is deleted automatically after 90 days.</p>

  <h2>7. Your rights</h2>
  <p>You may ask to see, correct or delete your personal information, or object to how it is used, subject to legal and operational requirements.</p>

  <h2>8. Contact</h2>
  <p>For privacy questions or requests, contact your system administrator or the organization's Data Protection Officer.</p>

  <p style="margin-top:28px">See also our <a href="{{ route('terms') }}">Terms of Service</a>.</p>
@endsection
