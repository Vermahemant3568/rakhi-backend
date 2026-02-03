<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans; }
        h1 { color: #E91E63; }
        p { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

<h1>Rakhi AI – Fitness Plan</h1>

<p><strong>Name:</strong> {{ $user->profile->first_name }}</p>
<p><strong>Date:</strong> {{ $date }}</p>
<p><strong>Fitness Goal:</strong> {{ $goal }}</p>

<hr>

<h3>Weekly Exercise Schedule</h3>
<table>
    <tr>
        <th>Day</th>
        <th>Exercise</th>
        <th>Duration</th>
        <th>Sets/Reps</th>
    </tr>
    @foreach($exercises as $exercise)
    <tr>
        <td>{{ $exercise['day'] }}</td>
        <td>{{ $exercise['name'] }}</td>
        <td>{{ $exercise['duration'] }}</td>
        <td>{{ $exercise['sets'] }}</td>
    </tr>
    @endforeach
</table>

<h3>Fitness Tips</h3>
<ul>
@foreach($tips as $tip)
    <li>{{ $tip }}</li>
@endforeach
</ul>

<p><em>This fitness plan is for general wellness. Consult a fitness professional for personalized training.</em></p>

</body>
</html>