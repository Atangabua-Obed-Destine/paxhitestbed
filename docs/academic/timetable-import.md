# Importing a semester's class timetable

The timetable can be loaded from a spreadsheet instead of being typed in, class by class. The import shows everything it would do before anything is saved.

## 1. Fill in the spreadsheet

Save it as **CSV (UTF-8)**, with one row per class and these columns:

| Column | What to put | Example |
|---|---|---|
| semester | The semester's name, exactly as it appears on the system | `FIRST SEMESTER - Year 1` |
| programmes | Programme shortcodes separated by `;`. Use one row for a class several programmes attend together | `HND - ACC;HND - MKT` |
| day | The day in full | `Tuesday` |
| start, end | 24-hour times | `08:00`, `10:00` |
| course | The course code on the system | `MGT1101H` |
| lecturer | The lecturer's staff ID. The lecturer needs the **Lecturer** role | `PAX0013` |
| room | The room's name, exactly as on the system | `SMBS HALL` |
| source | Anything you want to note (optional) | `from the Word timetable` |

**Joint classes:** put all the programmes on one row. Each programme then gets the code it takes for that course.

- If the code belongs to a **course group** (Academic > Course groups), each programme receives its own course from the group. For example, AGE gets AGE118H and ACC gets MGT1101H.
- A code shared by every programme, such as ENG1101H, stays as it is.

Leave out breaks, club activities and tutorials. They are not courses.

## 2. Preview

```
php artisan timetable:import path/to/timetable.csv --session=2026/2027 --section=All
```

The preview saves nothing. It lists:

- the classes each programme would receive, with their courses;
- **left out:** classes whose programme does not take that course in that semester (fix the programme's courses under Academic > Enroll courses), names the system cannot find, and slots the programme already uses;
- **clashes:** rooms or lecturers booked twice at the same time. One course taught to several programmes at once is not a clash.

## 3. Save

Run the same command with `--apply` added. It is safe to run again: classes already saved are counted, not doubled.

## The 2026/2027 first semester (done 25 Nov 2026)

- **What was imported:** 345 classes from the two Word timetables (Year 1: 4 timetables; Year 2: 11).
- **Rooms:** the Word timetables named none.
  - Year 1 uses each faculty's hall: SMBS, FAFS, FMS and SCEDT.
  - Year 2 uses placeholder rooms such as `SMBS HALL - Year 2 (ACC)`. Rename them under Academic > Rooms once the real rooms are known.
- **Left out:**
  - breaks, club activities, tutorials and SWE/CSN "Practicals";
  - Year 1 CPT/AGE "AGE119 Management of an Agro-Pastoral Enterprise", which is not a course on the system.
- **Two clashes kept as written:**
  - Mr. Ashu teaches Year 1 and Year 2 Agro-Pastoral Practicals on Thursday 3–5.
  - Dr. Aseh teaches CPT Roots & Tuber and AGPT Cereal/Roots on Tuesday 1–3.
