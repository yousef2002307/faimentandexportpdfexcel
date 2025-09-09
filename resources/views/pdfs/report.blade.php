<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 10px; 
            margin: 0;
            padding: 0;
        }
        .page { page-break-after: always; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
            page-break-inside: auto;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 5px; 
            text-align: left; 
        }
        th { 
            background-color: #f2f2f2; 
        }
        .header { 
            margin-bottom: 20px; 
        }
        .footer { 
            text-align: center; 
            margin-top: 20px; 
            font-size: 9px;
        }
        tr { 
            page-break-inside: avoid;
            page-break-after: auto;
        }
    </style>
</head>
<body>
    @if($isFirstChunk)
    <div class="header">
        <h1>Company Report: {{ date('F d, Y') }}</h1>
        <p>This report contains a full list of all records generated in a batch process.</p>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            {!! $rows !!}
        </tbody>
    </table>

    @if($isLastChunk)
    <div class="footer">
        <p>Page <span class="pageNumber"></span> of <span class="totalPages"></span></p>
    </div>
    @endif
</body>
</html>