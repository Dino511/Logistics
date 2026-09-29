@extends('legal.layout')
@section('title', 'Terms of Service')

@section('content')
  <p>These Terms of Service govern your use of the Logistics system (the "System"). By signing in, you agree to follow them.</p>

  <h2>1. Authorized use</h2>
  <p>The System is for authorized staff only, for managing shipments, vehicles, drivers and related work. Use it only for tasks assigned to your role.</p>

  <h2>2. Your account</h2>
  <ul>
    <li>Keep your password private and do not share your account.</li>
    <li>You are responsible for actions taken under your account.</li>
    <li>Tell an administrator right away if you think your account has been misused.</li>
  </ul>

  <h2>3. Acceptable use</h2>
  <p>Do not enter false information, try to access data outside your role, disrupt the System, or copy data out of it for purposes unrelated to your work.</p>

  <h2>4. Data accuracy</h2>
  <p>Enter shipment, delivery and fleet information accurately and keep it up to date. Route maps, distances and travel times are estimates and may not reflect actual road or traffic conditions.</p>

  <h2>5. Monitoring</h2>
  <p>Sign-ins and key actions are recorded in an activity log for security and auditing.</p>

  <h2>6. Suspension</h2>
  <p>Administrators may deactivate accounts that break these terms or are no longer needed.</p>

  <h2>7. Changes</h2>
  <p>These terms may be updated. Continuing to use the System after a change means you accept the updated terms.</p>

  <h2>8. Contact</h2>
  <p>For questions about these terms, contact your system administrator.</p>

  <p style="margin-top:28px">See also our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
@endsection
