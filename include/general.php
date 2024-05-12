<?php

// Функция для создания вариантов в выпадающем списке
function generateOptions($result, $defaultOption) {
    if ($result->num_rows > 0) {
        // Вывод значений в выпадающий список
        while ($row = $result->fetch_assoc()) {
            echo '<option value="'.$row[$defaultOption].'">'.$row[$defaultOption].'</option>';
        }
    } else {
        echo '<option value="">Нет данных</option>';
    }
}

function getListbyQuery($conn, $sql) {
    $results = $conn->query($sql);

    $list = [];

    if ($results->num_rows > 0) {
        // Записываем данные о предметах в массив
        while ($row = $results->fetch_assoc()) {
            $list[] = $row;
        }
    }
    
    return $list;
}

function getStudentMeanValue($conn, $studentId) {
    $sql = "SELECT AVG(grade) as mean
            FROM grades
            WHERE student_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $row = $result->fetch_assoc();
    
    return $row['mean'];    
}

function getStudentDebtListValue($conn, $studentId) {
    $sql_info = "SELECT subjects.name
                          FROM grades
                          JOIN schedule_dates ON schedule_dates.schedule_date_id = grades.schedule_date_id
                          JOIN schedules ON schedules.schedule_id = schedule_dates.schedule_id
                          JOIN subjects ON subjects.subject_id = schedules.subject_id
                          WHERE grades.student_id = ? AND grade = ?
                          ORDER BY subjects.name ASC";

    $gradeDebt = 'незачет';
    
    $stmt = $conn->prepare($sql_info);
    $stmt->bind_param("is", $studentId, $gradeDebt);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $debts = [];

    while ($row = $result->fetch_assoc()) {
        $debts[] = $row['name'];
    }
    
    return $debts;
}
