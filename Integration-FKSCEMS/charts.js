//Bar Chart

const attendanceRate = {
    labels: barChartLabels,
    datasets: [{
        label: 'Points',
        data: barChartData,
        backgroundColor: '#2563EB'
    }]
};

const barConfig = {
    type: 'bar',
    data: attendanceRate,
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                min: 0,
                max: 100,
                ticks: {
                    stepSize: 20,
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        },
        onClick: (event, elements) => {
        if (elements.length > 0) {
            const index = elements[0].index;
            const clubName = barChartLabels[index];
            window.location.href = 'dashboard.php?club_name=' + encodeURIComponent(clubName);
        }
        }
    }
};

new Chart(
    document.getElementById('barChart'),
    barConfig
);

//Donut Chart

const participationStatus = {
  labels: donutChartLabels,
  datasets: [
    {
      label: 'Present',
      data: donutChartData,
      backgroundColor: 
      [
        '#2563EB',
        '#22C55E',
        '#F59E0B',
        '#EF4444'
      ],
    }
  ]
};

const donutConfig = {
  type: 'doughnut',
  data: participationStatus,
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: 'right',
      },
      title: {
        display: false,
        text: 'Participation Status'
      }
    }
  },
};

new Chart(
    document.getElementById('donutChart'),
    donutConfig
);

//Line Chart

const monthlyParticipation = {
  labels: lineChartLabels,
  datasets: [
    {
      label: 'Dataset 1',
      data: lineChartData,
      borderColor: '#eb2525',
      backgroundColor: 'rgba(235, 37, 37, 0.2)',
    }
  ]
};

const lineConfig = {
  type: 'line',
  data: monthlyParticipation,
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: 'top',
      },
      title: {
        display: false,
        text: 'Chart.js Line Chart'
      }
    }
  },
};

new Chart(
    document.getElementById('lineChart'),
    lineConfig
);

//Event Chart (Bar Chart after)

if (document.getElementById('eventChart')) {
    const eventConfig = {
        type: 'bar',
        data: {
            labels: eventChartLabels,
            datasets: [
                {
                    label: 'Participants',
                    data: eventChartData,
                    backgroundColor: '#2563EB'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };

    new Chart(
        document.getElementById('eventChart'),
        eventConfig
    );
}