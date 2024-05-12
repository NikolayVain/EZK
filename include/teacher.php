<?php

if (empty($included)) die('No access');

// Получение текущего преподавателя из сессии
$teacher_id = $user['teacher_id'];

// process SUBJECTS 
// Подготовка SQL-запроса для получения списка предметов текущего преподавателя
$sql_subjects = "SELECT DISTINCT subjects.subject_id, subjects.name AS subject_name, subjects.control_type AS control_type
        FROM schedules
        INNER JOIN subjects ON schedules.subject_id = subjects.subject_id
        WHERE schedules.teacher_id = $teacher_id order by subject_name ASC";

$subjects = getListbyQuery($conn, $sql_subjects); // Создаем массив для хранения данных о предметах

$sql_groups = "SELECT DISTINCT student_groups.group_id, student_groups.number AS number
        FROM schedules
        INNER JOIN student_groups ON schedules.group_id = student_groups.group_id
        WHERE schedules.teacher_id = $teacher_id order by number ASC";

$groups = getListbyQuery($conn, $sql_groups);

$sql_semesters = "SELECT DISTINCT semesters.semester_id, semesters.number AS number
        FROM schedules
        INNER JOIN semesters ON schedules.semester_id = semesters.semester_id
        WHERE schedules.teacher_id = $teacher_id order by number ASC";

$semesters = getListbyQuery($conn, $sql_semesters);

// Получение выбранного предмета из запроса POST и сохранение в сессию
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['subject'])) {
        $_SESSION['selected_subject'] = $_POST['subject'];
    }
    
    if (!empty($_POST['group'])) {
        $_SESSION['selected_group'] = $_POST['group'];
    }
    
    if (!empty($_POST['semester'])) {
        $_SESSION['selected_semester'] = $_POST['semester'];
    }
    
    if (!empty($_POST['schedule_id'])) {
        if (!empty($_POST['grades'])) {
            $gradesToSave = json_decode($_POST['grades']);
       
            if ($gradesToSave) {
                foreach ($gradesToSave as $identifier => $grade) {
                    list($studentId, $dateId) = explode('-', $identifier);

                    if ($studentId and $dateId and $grade) {
                        // check old record 
                        
                        $sql_find = "SELECT *
                                              FROM grades
                                              WHERE student_id = ? AND schedule_date_id = ?";

                        $stmt = $conn->prepare($sql_find);
                        $stmt->bind_param("ii", $studentId, $dateId);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        $row = $result->fetch_assoc();
                        
                        if ($row) {
                            // update record
                            $sql_grades_update= "UPDATE grades 
                                                  SET grade = ?
                                                  where grade_id = ?";

                            $stmt = $conn->prepare($sql_grades_update);
                            $stmt->bind_param("si", $grade, $row['grade_id']);
                            $stmt->execute();                
                        } else {
                            // insert record
                            $sql_grades_insert = "INSERT INTO grades
                                                  (student_id, grade, schedule_date_id)
                                                  VALUES (?, ?, ?)";

                            $stmt = $conn->prepare($sql_grades_insert);
                            $stmt->bind_param("isi", $studentId, $grade, $dateId);
                            $stmt->execute();                
                        }
                    }
                }
            }
        }
        
        if (!empty($_POST['notes'])) {
            $notesToSave = json_decode($_POST['notes']);

            if ($notesToSave) {
                foreach ($notesToSave as $identifier => $note) {
                    if ($identifier and $note) {
                        // check old record 
                        
                        $sql_find = "SELECT *
                                              FROM notes
                                              WHERE schedule_date_id = ?";

                        $stmt = $conn->prepare($sql_find);
                        $stmt->bind_param("i", $identifier);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        $row = $result->fetch_assoc();
                        
                        if ($row) {
                            // update record
                            $sql_grades_update= "UPDATE notes 
                                                  SET text = ?
                                                  where note_id = ?";

                            $stmt = $conn->prepare($sql_grades_update);
                            $stmt->bind_param("si", $note, $row['note_id']);
                            $stmt->execute();                
                        } else {
                            // insert record
                            $sql_grades_insert = "INSERT INTO notes
                                                  (schedule_date_id, text)
                                                  VALUES (?, ?)";

                            $stmt = $conn->prepare($sql_grades_insert);
                            $stmt->bind_param("is", $identifier, $note);
                            $stmt->execute();                
                        }
                    }
                }
            }
        }
    }
}

if (isset($_SESSION['selected_subject'])) {
    $selected_subject_id = $_SESSION['selected_subject'];
} else {
    $selected_subject_id = $subjects[0]['subject_id'];
}

$selected_subjects_info = [];

foreach ($subjects as $item) {
    if ($item['subject_id'] == $selected_subject_id) {
        $selected_subjects_info = [$item];
    }
}

if (isset($_SESSION['selected_group'])) {
    $selected_group_id = $_SESSION['selected_group'];
} else {
    $selected_group_id = $groups[0]['group_id'];
}

if (isset($_SESSION['selected_semester'])) {
    $selected_semester_id = $_SESSION['selected_semester'];
} else {
    $selected_semester_id = $semesters[0]['semester_id'];
}

// SQL-запрос для получения дат занятий
$sql_dates = "SELECT schedule_dates.schedule_date_id, schedule_dates.date, schedule_dates.schedule_id, schedule_dates.type 
                      FROM schedule_dates
                      JOIN schedules ON schedule_dates.schedule_id = schedules.schedule_id
                      JOIN student_groups ON schedules.group_id = student_groups.group_id
                      JOIN semesters ON schedules.semester_id = semesters.semester_id
                      WHERE schedules.teacher_id = ? AND student_groups.group_id = ? AND semesters.semester_id = ? AND subject_id = ?
                      ORDER BY date ASC";

$stmt = $conn->prepare($sql_dates);
$stmt->bind_param("iiii", $teacher_id, $selected_group_id, $selected_semester_id, $selected_subject_id);
$stmt->execute();
$result_lessons_dates = $stmt->get_result();

$dates = [];
$dateTotal = [];
$schedule_id = null;

while ($row = $result_lessons_dates->fetch_assoc()) {
    if ($row['type'] == 'lesson') {
        $dates[$row['schedule_date_id']] = date("d.M.Y", strtotime($row['date'])); // Преобразование дат занятий
    } else {
        $dateTotal[$row['schedule_date_id']] = date("d.M.Y", strtotime($row['date'])); // Преобразование дат занятий
    }
    
    $schedule_id = $row['schedule_id'];
}

// get grades
$grades = [];

$sql = "SELECT grades.grade, grades.schedule_date_id, grades.student_id
            FROM grades";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $grades[$row['student_id']][$row['schedule_date_id']] = $row['grade'];
}

// get notes
$notes = [];

$sql = "SELECT notes.text, notes.schedule_date_id
            FROM notes";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notes[$row['schedule_date_id']] = $row['text'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет преподавателя</title>
    <link rel="stylesheet" href="css/prepodavatel.css"> <!-- Путь к  CSS файлу -->
    <link rel="icon" href="img/academ_photo-resizer.ru.png" type="image/png">
    <style>
    /* Добавленные стили для фонового цвета */
    .bg-FFE588 { background-color: #FFE588; }
    .bg-AFEEEE { background-color: #AFEEEE; }
    .bg-BADBAD { background-color: #BADBAD; }
    .bg-CCCCFF { background-color: #CCCCFF; }
    .bg-FFBCAD { background-color: #FFBCAD; }
    .bg-E4717A { background-color: #E4717A; }
    .bg-F5F5DC { background-color: #F5F5DC; }

    /* стили для окна с преподавателем */
    .nameteacher{
        color: #81C5FF;
        text-align: left;
        padding: 10px;
        border-radius: 15px 0 0 15px;
        top: 0%;
    }
</style>

</head>
<body>
    <div class="header">
        <h1>Личный кабинет преподавателя</h1>
    </div>
    <div class="nameteacher">
        <h1><?php echo isset($user['teacher_name']) ? $user['teacher_name'] : ''; ?></h1>
    </div>

    <div class="filters">
    <form action="" method="post">
        <label for="group">Группа:</label>
        <select id="group" name="group">
            <?php foreach ($groups as $item): ?>
                <option value="<?php echo $item['group_id']; ?>" <?php if ($item['group_id'] == $selected_group_id) echo 'selected'; ?> ><?php echo $item['number']; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="semester">Семестр:</label>
        <select id="semester" name="semester">
            <?php foreach ($semesters as $item): ?>
                <option value="<?php echo $item['semester_id']; ?>" <?php if ($item['semester_id'] == $selected_semester_id) echo 'selected'; ?> ><?php echo $item['number']; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="subject">Предмет:</label>
        <select id="subject" name="subject">
            <?php foreach ($subjects as $item): ?>
                <option value="<?php echo $item['subject_id']; ?>" <?php if ($item['subject_id'] == $selected_subject_id) echo 'selected'; ?> ><?php echo $item['subject_name']; ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Применить</button>
    </form>
</div>

<form action="" method="post" id="grades-form">
    
<div class="content">
    <div class="data-table">
        <table id="students-table">
            <thead>
                <tr>
                    <th>Список студентов</th>
                    <?php foreach ($dates as $lesson_date): ?>
                        <th><?php echo $lesson_date; ?></th>
                    <?php endforeach; ?>
                    <th>Аттестация: <?php echo current($dateTotal); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                
                echo '<tr>';
                echo '<td>&nbsp;&nbsp;&nbsp;<i>Заметки</i></td>';
                
                foreach ($dates as $id => $lesson_date) {
                    echo '<td class="note" contenteditable="true" data-identifier="'.$id.'">';
                    if (!empty($notes[$id])) echo $notes[$id];
                    echo '</td>';
                }
                
                echo '</tr>';
                
                if ($selected_group_id) {
                    $sql = "SELECT students.student_id, students.full_name FROM students
                            INNER JOIN student_groups ON students.group_id = student_groups.group_id
                            WHERE student_groups.group_id = '$selected_group_id'
                            ORDER BY students.full_name ASC";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr><td>'.$row['full_name'].'</td>';
                            foreach ($dates as $id => $lesson_date) {
                                echo '<td class="grade" contenteditable="true" data-identifier="'.$row['student_id'].'-'.$id.'">';
                                
                                if (!empty($grades[$row['student_id']][$id])) {
                                    echo $grades[$row['student_id']][$id];
                                }
                                
                                echo '</td>';
                            }
                            
                            
                            echo '<td class="grade" contenteditable="true" data-identifier="'.$row['student_id'].'-'.key($dateTotal).'">';
                            
                            if (!empty($grades[$row['student_id']][key($dateTotal)])) {
                                echo $grades[$row['student_id']][key($dateTotal)];
                            }
                            
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                    } else {
                        // Обрабатываем случай, когда результатов нет
                        echo "<tr><td colspan='".(count($dates) + 2)."'>Нет результатов</td></tr>"; // Учитываем дополнительный столбец для даты аттестации
                    }
                } else {
                    // Сообщение пользователю о необходимости выбрать группу
                    echo "<tr><td colspan='".(count($dates) + 2)."'>Выберите группу</td></tr>"; // Учитываем дополнительный столбец для даты аттестации
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
    
<input type="hidden" name="grades" value="">
<input type="hidden" name="notes" value="">
<input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
<div class="parent-container">
  <button type="submit" onclick="return sendGradesForm();">Сохранить</button>
</div>

</form>    
    
<div class="subject-info">
    <?php if (!empty($selected_subjects_info)): ?>
        <div class="subject-pair">
            <?php foreach ($selected_subjects_info as $subject): ?>
            <div class="subject-window">
                <h2>Предмет:</h2>
                <p id="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
            </div>
            <div class="control-type-window">
                <h2>Вид контроля:</h2>
                <p id="control-type"><?php echo htmlspecialchars($subject['control_type']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Кнопка "Выход" -->
<div class="logout-container">
    <form action="logout.php" method="post">
        <button  type="submit">Выход</button>
    </form>
</div>


    <div class="footer">
        <p>&copy; 2024 Личный кабинет преподавателя</p>
    </div>

    <script>
        // Функция для установки цвета фона в зависимости от значения в ячейке
        function setColorByGrade(gradeCell, grade) {
            var colors = {
                'отсутствует': 'bg-F5F5DC',
                'неизвестно': 'bg-E4717A',
                'зачет': 'bg-CCCCFF',
                'незачет': 'bg-FFBCAD',
                '3': 'bg-FFE588',
                '4': 'bg-AFEEEE',
                '5': 'bg-BADBAD'
            };

            // Удаляем предыдущий фоновый цвет
            gradeCell.classList.remove(...Object.values(colors));
            // Применяем новый фоновый цвет
            if (colors[grade]) {
                gradeCell.classList.add(colors[grade]);
            }
        }
        
        // Функция для проверки введенной оценки
        function sendGradesForm() {
            var form = document.getElementById('grades-form');
            
            var valuesGrades = {};
            
            form.querySelectorAll('.grade').forEach(function(cell) {
                valuesGrades[cell.dataset.identifier] = cell.textContent.trim().toLowerCase();
            });
            
            form.elements['grades'].value = JSON.stringify(valuesGrades);

            var valuesNotes = {};
            
            form.querySelectorAll('.note').forEach(function(cell) {
                valuesNotes[cell.dataset.identifier] = cell.textContent.trim().toLowerCase();
            });
            
            form.elements['notes'].value = JSON.stringify(valuesNotes);

            // todo - add check???

            return true;
        }

        // Функция для проверки введенной оценки
        function validateGrade(cell) {
            let text = cell.textContent.trim().toLowerCase();
            switch (text) {
                case 'о':
                    cell.textContent = 'отсутствует';
                    break;
                case '?':
                    cell.textContent = 'неизвестно';
                    break;
                case 'з':
                    cell.textContent = 'зачет';
                    break;
                case 'н':
                    cell.textContent = 'незачет';
                    break;
                case '3':
                case '4':
                case '5':
                    // Для числовых оценок никаких изменений не требуется
                    break;
                default:
                    cell.textContent = ''; // Очищаем ячейку, если ввод не соответствует ожидаемому
                    break;
            }
            setColorByGrade(cell, cell.textContent.trim().toLowerCase()); // Обновляем фоновый цвет ячейки
        }

        // Добавляем обработчик события для ячеек с оценками
        document.querySelectorAll('.grade').forEach(function(cell) {
            cell.addEventListener('keydown', function(event) {
                var allowedKeys = ['Backspace', 'Delete', '3', '4', '5', 'о', 'з', 'н', '?'];
                if (!allowedKeys.includes(event.key.toLowerCase())) {
                    event.preventDefault(); // Предотвращаем ввод неразрешенных символов
                }
            });

            cell.addEventListener('input', function() {
                validateGrade(cell); // Проверяем и обновляем содержимое ячейки
            });
            
            setColorByGrade(cell, cell.textContent.trim().toLowerCase()); // Обновляем фоновый цвет ячейки
        });
    </script>
</body>
</html>
