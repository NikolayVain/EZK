<?php
if (empty($included)) die('No access');

$sql_semesters = "SELECT DISTINCT semesters.semester_id, semesters.number AS number
        FROM semesters order by number ASC";

$semesters = getListbyQuery($conn, $sql_semesters);

//todo - get from request
$selected_semester_id = 1;

// get student info

$sql_info = "SELECT student_groups.number as group_number, specialties.full_name as specialty_name, courses.number as course_number, faculties.full_name as faculty_name
                      FROM student_groups
                      JOIN specialties ON specialties.specialty_id = student_groups.specialty_id
                      JOIN courses ON courses.course_id = student_groups.course_id
                      JOIN faculties ON faculties.faculty_id = specialties.faculty_id
                      WHERE student_groups.group_id = ?";

$stmt = $conn->prepare($sql_info);
$stmt->bind_param("i", $user['group_id']);
$stmt->execute();
$result = $stmt->get_result();

$studentInfo = $result->fetch_assoc();

if ($studentInfo) {
    // calculate mean grades
    
    $studentInfo['mean'] = getStudentMeanValue($conn, $user['student_id']);    
    
    // get debts list
    
    $studentInfo['debts'] = getStudentDebtListValue($conn, $user['student_id']);    
    
    // calculate rating
    
    $sql_group = "SELECT student_id
                          FROM students
                          WHERE group_id = ?";

    $stmt = $conn->prepare($sql_group);
    $stmt->bind_param("i", $user['group_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $studentGroupIds = [];

    while ($row = $result->fetch_assoc()) {
        $studentGroupIds[] = $row['student_id'];
    }
     
    if ($studentGroupIds) {
        $rating = [];
        
        foreach ($studentGroupIds as $studentId) {
            $key = str_pad(count(getStudentDebtListValue($conn, $studentId)), 3, '00', STR_PAD_LEFT);
            
            $key .= 5 - getStudentMeanValue($conn, $studentId);
            
            $rating[$studentId] = $key;
        }
        
        asort($rating);
        
        $studentInfo['rating'] = array_search($user['student_id'], array_keys($rating)) + 1;
    }
} else {
    die('Error: No data about student!!!');
}

// SQL-запрос для получения последней итоговой оценки по каждому предмету в семестре
$sql_lessons_dates = "SELECT MAX(schedule_dates.date) AS lesson_date, subjects.name, subjects.control_type, teachers.full_name, MAX(grades.grade) AS grade
                      FROM schedule_dates
                      JOIN schedules ON schedule_dates.schedule_id = schedules.schedule_id
                      JOIN subjects ON subjects.subject_id = schedules.subject_id
                      JOIN student_groups ON schedules.group_id = student_groups.group_id
                      JOIN semesters ON schedules.semester_id = semesters.semester_id
                      JOIN teachers ON schedules.teacher_id = teachers.teacher_id
                      LEFT JOIN grades ON grades.schedule_date_id = schedule_dates.schedule_date_id
                      WHERE student_groups.group_id = ? AND semesters.semester_id = ? AND schedule_dates.type = 'total'
                      GROUP BY subjects.subject_id, subjects.name, subjects.control_type, teachers.full_name
                      ORDER BY lesson_date";

$stmt = $conn->prepare($sql_lessons_dates);
$stmt->bind_param("ii", $user['group_id'], $selected_semester_id);
$stmt->execute();
$result_lessons = $stmt->get_result();

$lessons = [];

while ($row = $result_lessons->fetch_assoc()) {
    $lessons[] = [
        'name'          => $row['name'],
        'control_type'  => $row['control_type'],
        'date'          => date("d.M.Y", strtotime($row['lesson_date'])),
        'full_name'     => $row['full_name'],
        'grade'         => $row['grade'],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Студенческая информационная система</title>
    <style>
    </style>
    <link rel="stylesheet" href="css/stylestudent.css"> <!-- Путь к CSS файлу -->
    <link rel="icon" href="img/academ_photo-resizer.ru.png" type="image/png">
    
</head>
<body>
    
    <div class="header"></div>
    <h1>Личный кабинет студента</h1>
    <!-- Вставка изображения -->
    

    <!-- Отображение фамилии, имени и отчества студента -->
    <div class="fio">
        <h2><?php echo isset($user['full_name']) ? $user['full_name'] : ''; ?></h2>
    </div>

    <!-- Кнопка "Выход" -->
    <div class="logout-container">
        <form action="logout.php" method="post">
            <button id="click-me" type="submit">Выход</button>
        </form>
    </div>
    
    <!-- Фильтр по семестрам -->
    <div class="select-container">
        <label for="semester">Выберите семестр:</label>
        <select id="semester" name="semester">
            <?php foreach ($semesters as $item): ?>
                <option value="<?php echo $item['semester_id']; ?>" <?php if ($item['semester_id'] == $selected_semester_id) echo 'selected'; ?> ><?php echo $item['number']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Таблица с данными внутри одного семестра -->

    <div class="window" id="window-1">
        <table class="table-headers">
            <thead>
                <tr>
                    <th>Название дисциплины</th>
                    <th>Вид контроля</th>
                    <th>Дата аттестации</th>
                    <th>Преподаватель</th>
                    <th>Оценка</th>
                </tr>
            </thead>
            <?php foreach ($lessons as $item): ?>
                <tr class="column">
                <?php foreach ($item as $value): ?>
                    <td>
                        <?php echo $value; ?>
                    </td>
                <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    
    
    <!-- Информация о студенте -->
    <div class="content-container">
    <div class="window" id="window-2">
        <div class="student-info">
            <div class="info-pair">
                <div class="info-item fixed-width">
                    <p>Факультет: <br><?php echo $studentInfo['faculty_name']; ?></p>
                </div>

                <div class="info-item fixed-width">
                    <p>Направление подготовки: <br><?php echo $studentInfo['specialty_name']; ?></p>
                </div>
            </div>

            <div class="info-pair">
                <div class="info-item fixed-width">
                    <p>Номер курса: <br><?php echo $studentInfo['course_number']; ?></p>
                </div>

                <div class="info-item fixed-width">
                    <p>Группа: <br><?php echo $studentInfo['group_number']; ?></p>
                </div>
            </div>

            <div class="info-item fixed-width2">
                <p>Средний балл: <?php echo isset($studentInfo['mean']) ? round($studentInfo['mean'], 2) : 'Нет данных'; ?></p>
            </div>

            <div class="info-item fixed-width2">
                <p>Рейтинг в группе: <?php echo $studentInfo['rating']; ?></p>
            </div>
            <div class="info-item flexible-width">
                <p>Задолжности: <br>
                <ul>    
                <?php foreach ($studentInfo['debts'] as $item): ?>
                    <li><?php echo $item; ?></li>
                <?php endforeach; ?>
                </ul> 
                </p>
            </div>
        </div>
        </div>
    </div>
    
        <div class="inf">
            <h3>Информация:</h3>
        </div>
    </div>
    <div class="LKS">
    <h1>Личный кабинет студента</h1>
    </div>
    <div class="footer"></div>
    <div class="logoezk-image"></div>





    <div class="mode-switch">
        <button id="infoBtn">Информация</button>
        <button id="gradeBookBtn">Зачетная книжка</button>
    </div>

        <!-- Скрипт для переключения режимов отображения -->
        <script>
    document.addEventListener('DOMContentLoaded', function() {
        const infoBtn = document.getElementById('infoBtn');
        const gradeBookBtn = document.getElementById('gradeBookBtn');
        const window1 = document.getElementById('window-1');
        const window2 = document.getElementById('window-2');
        const selectContainer = document.querySelector('.select-container');
        const circleLogo = document.querySelector('.logoezk-image')
const exitButton = document.getElementById('click-me')
        // Обработчики клика по кнопкам
        infoBtn.addEventListener('click', function() {
            window1.style.display = 'none';
            window2.style.opacity= '1';
            window2.style.display = 'block';
            infoBtn.style.backgroundColor = '#348AF7'
            infoBtn.style.color = '#ffff'
            gradeBookBtn.style.color = 'black'
            gradeBookBtn.style.backgroundColor = 'transparent'
            window2.style.left='60px'
            window2.style.top='160px'
            selectContainer.style.display = 'none';
            exitButton.style.marginLeft = '60px'
            exitButton.style.top = '42%'
            circleLogo.style.top = '48%'
        });

        gradeBookBtn.addEventListener('click', function() {
            circleLogo.style.top = '53%'
            exitButton.style.top = '48%'
            window2.style.opacity= '1';
            gradeBookBtn.style.color = '#ffff'
            infoBtn.style.color = 'black'
            gradeBookBtn.style.backgroundColor = '#348AF7'
            infoBtn.style.backgroundColor = 'transparent'
            
            window1.style.display = 'block';
            window2.style.display = 'none';
            
            selectContainer.style.display = 'block';
            exitButton.style.marginLeft = '45px'
        });
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1701) {
            location.reload();
        }
    });
</script>

</body>
</html>

