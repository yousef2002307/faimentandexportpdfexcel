@foreach($records as $record)
    <tr>
        <td>{{ $record->id }}</td>
        <td>{{ $record->name }}</td>
        <td>{{ $record->email }}</td>
        <td>{{ $record->created_at }}</td>
    </tr>
@endforeach