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

<h1>Rakhi AI – Diet Plan</h1>

<p><strong>Name:</strong> {{ $user->profile->first_name }}</p>
<p><strong>Date:</strong> {{ $date }}</p>
<p><strong>Goal:</strong> {{ $goal }}</p>

<hr>

<h3>Daily Meal Plan</h3>
<table>
    <tr>
        <th>Meal</th>
        <th>Food Items</th>
        <th>Portion</th>
    </tr>
    @foreach($meals as $meal)
    <tr>
        <td>{{ $meal['time'] }}</td>
        <td>{{ $meal['items'] }}</td>
        <td>{{ $meal['portion'] }}</td>
    </tr>
    @endforeach
</table>

<h3>Nutritional Guidelines</h3>
<ul>
@foreach($guidelines as $guideline)
    <li>{{ $guideline }}</li>
@endforeach
</ul>

<p><em>This diet plan is for general wellness. Consult a nutritionist for medical dietary needs.</em></p>

</body>
</html>