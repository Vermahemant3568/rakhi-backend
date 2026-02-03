<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans; }
        h1 { color: #E91E63; }
        p { font-size: 14px; }
    </style>
</head>
<body>

<h1>Rakhi AI – Consultation Summary</h1>

<p><strong>Name:</strong> {{ $user->profile->first_name }}</p>
<p><strong>Date:</strong> {{ $date }}</p>

<hr>

<h3>Key Discussion</h3>
<p>{{ $summary }}</p>

<h3>Rakhi's Notes</h3>
<ul>
@foreach($notes as $note)
    <li>{{ $note }}</li>
@endforeach
</ul>

<p><em>This report provides lifestyle guidance only. Rakhi is not a medical professional.</em></p>

</body>
</html>